<?php
/**
 * DNS Pro - camada de provisionamento compartilhada entre o addon whmcs_dns
 * e o modulo de servidor dnspro.
 *
 * Nao depende de nada especifico de provedor: monta o config a partir das
 * configuracoes do addon e usa o Cardo (namingo/plexdns) para criar/apagar zona.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

function whmcs_dnspro_settings(): array
{
    $out = [];
    foreach (Capsule::table('tbladdonmodules')->where('module', 'whmcs_dns')->get() as $row) {
        $out[(string) $row->setting] = $row->value;
    }
    return $out;
}

function whmcs_dnspro_provider_config(array $settings, string $domain): array
{
    return [
        'domain_name' => $domain,
        'provider' => (string) ($settings['provider'] ?? ''),
        'apikey' => (string) ($settings['apikey'] ?? ''),
        'ns1' => (string) ($settings['ns1'] ?? ''),
        'ns2' => (string) ($settings['ns2'] ?? ''),
        'soa_email' => (string) ($settings['soa_email'] ?? ''),
        'cloudns_auth_id' => (string) ($settings['cloudns_auth_id'] ?? ''),
        'cloudns_auth_password' => (string) ($settings['cloudns_auth_password'] ?? ''),
        'project_id' => (string) ($settings['scaleway_project_id'] ?? ''),
        'parent_domain' => (string) ($settings['scaleway_parent_domain'] ?? ''),
        'sharing_id' => (string) ($settings['gandi_sharing_id'] ?? ''),
        'auth_scheme' => (string) ($settings['gandi_auth_scheme'] ?? 'Bearer'),
        'serverid' => (int) ($settings['anycast_server_id'] ?? 0),
    ];
}

function whmcs_dnspro_cardo()
{
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }
    return new \Namingo\Cardo\DNS\Service(Capsule::connection()->getPdo());
}

/**
 * Cria a zona e tenta apontar o dominio para os nameservers do DNS Pro.
 * Retorna ['ok'=>bool, 'error'=>string, 'zone_id'=>int|null, 'ns_ok'=>bool].
 */
function whmcs_dnspro_create_zone(string $domain, int $clientId, int $serviceId = 0): array
{
    $domain = strtolower(trim($domain));
    if ($domain === '') {
        return ['ok' => false, 'error' => 'dominio vazio', 'zone_id' => null, 'ns_ok' => false];
    }

    $settings = whmcs_dnspro_settings();
    $config = whmcs_dnspro_provider_config($settings, $domain);

    if ($config['provider'] === '' || $config['apikey'] === '') {
        return ['ok' => false, 'error' => 'provider/apikey nao configurados no addon', 'zone_id' => null, 'ns_ok' => false];
    }

    try {
        $cardo = whmcs_dnspro_cardo();
        $cardo->createDomain([
            'client_id' => $clientId,
            'config' => json_encode($config, JSON_UNESCAPED_SLASHES),
        ]);
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage(), 'zone_id' => null, 'ns_ok' => false];
    }

    $zone = Capsule::table('zones')->where('domain_name', $domain)->first();
    if (!$zone) {
        return ['ok' => false, 'error' => 'zona nao encontrada no banco apos criar', 'zone_id' => null, 'ns_ok' => false];
    }

    $domainRow = Capsule::table('tbldomains')
        ->select('id', 'status')
        ->where('userid', $clientId)
        ->where('domain', $domain)
        ->first();

    $stateRow = [
        'mode' => 'dnspro',
        'status' => 'active',
        'service_id' => $serviceId ?: null,
        'zone_id' => (int) $zone->id,
        'last_ns' => json_encode(array_values(array_filter([$config['ns1'], $config['ns2']])), JSON_UNESCAPED_SLASHES),
        'last_error' => null,
    ];
    if ($domainRow) {
        $stateRow['domain_id'] = (int) $domainRow->id;
        $stateRow['client_id'] = $clientId;
        Capsule::table('mod_whmcs_dns_domain_state')->updateOrInsert(
            ['domain_id' => (int) $domainRow->id],
            $stateRow
        );
    }

    $nsOk = false;
    if ($domainRow && (string) $domainRow->status === 'Active' && $config['ns1'] !== '' && $config['ns2'] !== '') {
        try {
            $res = localAPI('DomainUpdateNameservers', [
                'domainid' => (int) $domainRow->id,
                'ns1' => $config['ns1'],
                'ns2' => $config['ns2'],
            ]);
            $nsOk = (($res['result'] ?? '') === 'success');
            if (!$nsOk) {
                Capsule::table('mod_whmcs_dns_domain_state')
                    ->where('domain_id', (int) $domainRow->id)
                    ->update(['last_error' => mb_substr((string) ($res['error'] ?? $res['message'] ?? 'falha ao aplicar NS'), 0, 250)]);
            }
        } catch (\Throwable $e) {
            $nsOk = false;
        }
    }

    logActivity("WHMCS-DNS Pro: zona criada dominio={$domain} cliente={$clientId} servico={$serviceId} ns_aplicado=" . ($nsOk ? 'sim' : 'nao'));

    return ['ok' => true, 'error' => '', 'zone_id' => (int) $zone->id, 'ns_ok' => $nsOk];
}

/**
 * Remove a zona (local + provedor) de um dominio.
 */
function whmcs_dnspro_delete_zone(string $domain, int $clientId): array
{
    $domain = strtolower(trim($domain));
    $zone = Capsule::table('zones')->where('domain_name', $domain)->where('client_id', $clientId)->first();
    if (!$zone) {
        return ['ok' => true, 'error' => 'zona ja inexistente'];
    }

    $settings = whmcs_dnspro_settings();
    $config = whmcs_dnspro_provider_config($settings, $domain);

    try {
        $cardo = whmcs_dnspro_cardo();
        $cardo->deleteDomain(['config' => json_encode($config, JSON_UNESCAPED_SLASHES)]);
    } catch (\Throwable $e) {
        // segue removendo o mapeamento local, mas registra o erro
        logActivity("WHMCS-DNS Pro: falha ao apagar zona remota dominio={$domain}: " . mb_substr($e->getMessage(), 0, 200));
    }

    Capsule::table('records')->where('domain_id', $zone->id)->delete();
    Capsule::table('zones')->where('id', $zone->id)->delete();
    logActivity("WHMCS-DNS Pro: zona apagada dominio={$domain} cliente={$clientId}");

    return ['ok' => true, 'error' => ''];
}

function whmcs_dnspro_set_state(string $domain, int $clientId, string $status): void
{
    $domain = strtolower(trim($domain));
    $row = Capsule::table('tbldomains')->select('id')->where('userid', $clientId)->where('domain', $domain)->first();
    if ($row) {
        Capsule::table('mod_whmcs_dns_domain_state')
            ->where('domain_id', (int) $row->id)
            ->update(['status' => $status]);
    }
}
