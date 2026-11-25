<?php
/**
 * WHMCS-DNS module
 *
 * Written in 2025-2026 by Taras Kondratyuk (https://namingo.org)
 *
 * @license MIT
 * @see https://opensource.org/licenses/MIT
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;
use Namingo\Cardo\DNS\Service as CardoService;

define('WHMCSDNS_TABLE_ZONES', 'zones');
define('WHMCSDNS_TABLE_RECORDS', 'records');
define('WHMCSDNS_TABLE_THROTTLE', 'mod_whmcs_dns_throttle');

/**
 * Excecao de negocio: a mensagem e segura para o cliente final.
 * Qualquer outra excecao (provider, banco, rede) vira mensagem generica + log.
 */
final class WhmcsDnsUserException extends RuntimeException
{
}

/**
 * Traduz uma excecao em mensagem de tela.
 * Detalhe tecnico vai para Module Log e Activity Log, nunca para o cliente.
 */
function whmcs_dns_error_message(Throwable $e, array $context = []): array
{
    if ($e instanceof WhmcsDnsUserException) {
        return ['type' => 'error', 'text' => $e->getMessage()];
    }

    $domain = (string)($context['domain'] ?? '');
    $clientId = (int)($context['client_id'] ?? 0);
    $action = (string)($context['action'] ?? 'clientarea');
    $detail = $e->getMessage();

    // Sem payload e sem credenciais: apenas a mensagem da excecao, truncada.
    logModuleCall('whmcs_dns', $action, ['domain' => $domain, 'client_id' => $clientId], $detail);
    logActivity("WHMCS-DNS: falha em {$action} dominio={$domain} cliente={$clientId}: " . mb_substr($detail, 0, 500));

    return ['type' => 'error', 'text' => 'Nao foi possivel concluir a operacao. Tente novamente ou contate o suporte.'];
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

/**
 * Addon module config
 */
function whmcs_dns_config()
{
    return [
        'name'        => 'DNS Hosting',
        'description' => 'DNS management addon enabling zone and record control via external providers',
        'author'      => 'Namingo',
        'language'    => 'english',
        'version'     => '1.2.0',
        'fields'      => [
            'provider' => [
                'FriendlyName' => 'Provider',
                'Type'         => 'dropdown',
                'Options'      => [
                    'AnycastDNS'    => 'AnycastDNS',
                    'Bind'     => 'Bind',
                    'Bunny'     => 'Bunny',
                    'Cloudflare' => 'Cloudflare',
                    'ClouDNS'    => 'ClouDNS',
                    'Desec'     => 'Desec',
                    'DNSimple' => 'DNSimple',
                    'DigitalOcean' => 'DigitalOcean',
                    'GandiLiveDNS' => 'Gandi LiveDNS',
                    'Hetzner'    => 'Hetzner',
                    'PowerDNS'     => 'PowerDNS',
                    'Scaleway' => 'Scaleway',
                    'Vultr' => 'Vultr',
                ],
                'Default'      => 'Vultr',
                'Description'  => 'Select your DNS provider from the list. Ensure you have an account with the chosen service.',
            ],
            'apikey' => [
                'FriendlyName' => 'API Key',
                'Type'         => 'password',
                'Size'         => '50',
                'Default'      => '',
                'Description'  => "Enter your DNS provider's API key. Keep it confidential and ensure it's valid for requests.",
            ],
            'anycast_server_id' => [
                'FriendlyName' => 'AnycastDNS Server ID', 'Type' => 'text', 'Size' => '12',
                'Default' => '0',
                'Description' => 'Optional AnycastDNS server ID. Leave 0 unless your account requires a specific server.',
            ],
            'cloudns_auth_id' => [
                'FriendlyName' => 'ClouDNS Auth ID', 'Type' => 'text', 'Size' => '30',
                'Description' => 'Required only for ClouDNS.',
            ],
            'cloudns_auth_password' => [
                'FriendlyName' => 'ClouDNS Auth Password', 'Type' => 'password', 'Size' => '30',
                'Description' => 'Required only for ClouDNS.',
            ],
            'scaleway_project_id' => [
                'FriendlyName' => 'Scaleway Project ID', 'Type' => 'text', 'Size' => '50',
                'Description' => 'Required only for Scaleway.',
            ],
            'scaleway_parent_domain' => [
                'FriendlyName' => 'Scaleway Parent Domain', 'Type' => 'text', 'Size' => '50',
                'Description' => 'Optional. Usually leave empty for normal root-zone hosting.',
            ],
            'gandi_sharing_id' => [
                'FriendlyName' => 'Gandi Sharing ID', 'Type' => 'text', 'Size' => '50',
                'Description' => 'Optional Gandi organization sharing context.',
            ],
            'gandi_auth_scheme' => [
                'FriendlyName' => 'Gandi Auth Scheme',
                'Type' => 'dropdown',
                'Options' => ['Bearer' => 'Bearer (recommended)', 'Apikey' => 'Apikey (legacy)'],
                'Default' => 'Bearer',
                'Description' => 'Bearer is recommended for current Gandi personal access tokens.',
            ],
            'max_zones_per_client' => [
                'FriendlyName' => 'Maximum Zones Per Client', 'Type' => 'text', 'Size' => '8',
                'Default' => '5', 'Description' => '0 means unlimited. Applies when a client enables DNS.',
            ],
            'max_records_per_zone' => [
                'FriendlyName' => 'Maximum Records Per Zone', 'Type' => 'text', 'Size' => '8',
                'Default' => '100', 'Description' => '0 means unlimited. Applies when a client adds a record.',
            ],

            'soa_email' => [
                'FriendlyName' => 'SOA Email',
                'Type'         => 'text',
                'Size'         => '50',
                'Default'      => '',
                'Description'  => 'Email address for the responsible person of this DNS zone (used in SOA).',
            ],

            'bind_powerdns_api_ip' => [
                'FriendlyName' => 'BIND/PowerDNS API IP',
                'Type'         => 'text',
                'Size'         => '50',
                'Default'      => '127.0.0.1',
                'Description'  => 'IP address of your BIND/PowerDNS server where the API is accessible.',
            ],

            'ns1' => [
                'FriendlyName' => 'NS1',
                'Type'         => 'text',
                'Size'         => '50',
                'Default'      => '',
                'Description'  => 'Nameserver 1 for your DNS zone.',
            ],
            'ns2' => [
                'FriendlyName' => 'NS2',
                'Type'         => 'text',
                'Size'         => '50',
                'Default'      => '',
                'Description'  => 'Nameserver 2 for your DNS zone.',
            ],
            'ns3' => [
                'FriendlyName' => 'NS3',
                'Type'         => 'text',
                'Size'         => '50',
                'Default'      => '',
                'Description'  => 'Nameserver 3 for your DNS zone (optional).',
            ],
            'ns4' => [
                'FriendlyName' => 'NS4',
                'Type'         => 'text',
                'Size'         => '50',
                'Default'      => '',
                'Description'  => 'Nameserver 4 for your DNS zone (optional).',
            ],
            'ns5' => [
                'FriendlyName' => 'NS5',
                'Type'         => 'text',
                'Size'         => '50',
                'Default'      => '',
                'Description'  => 'Nameserver 5 for your DNS zone (optional).',
            ],
        ],
    ];
}

/**
 * Create DB tables
 */
function whmcs_dns_activate()
{
    try {
        if (!Capsule::schema()->hasTable(WHMCSDNS_TABLE_ZONES)) {
            Capsule::schema()->create(WHMCSDNS_TABLE_ZONES, function ($table) {
                /** @var \Illuminate\Database\Schema\Blueprint $table */
                $table->bigIncrements('id');
                $table->bigInteger('client_id')->unsigned()->index();
                $table->string('domain_name', 75)->nullable()->unique();
                $table->string('provider_id', 11)->nullable();
                $table->string('zoneId', 100)->nullable();
                $table->text('config');
                $table->dateTime('created_at')->useCurrent();
                $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        }

        if (!Capsule::schema()->hasTable(WHMCSDNS_TABLE_RECORDS)) {
            Capsule::schema()->create(WHMCSDNS_TABLE_RECORDS, function ($table) {
                /** @var \Illuminate\Database\Schema\Blueprint $table */
                $table->bigIncrements('id');
                $table->bigInteger('domain_id')->unsigned()->index();
                $table->string('recordId', 100)->nullable();
                $table->string('type', 10);
                $table->string('host', 255);
                $table->text('value');
                $table->integer('ttl')->nullable();
                $table->integer('priority')->nullable();
                $table->dateTime('created_at')->useCurrent();
                $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

                // Foreign keys are optional in many WHMCS installs; enable only if you know your DB config allows it.
                // $table->foreign('domain_id')->references('id')->on(WHMCSDNS_TABLE_ZONES)->onDelete('cascade');
            });
        }

        if (!Capsule::schema()->hasTable(WHMCSDNS_TABLE_THROTTLE)) {
            Capsule::schema()->create(WHMCSDNS_TABLE_THROTTLE, function ($table) {
                /** @var \Illuminate\Database\Schema\Blueprint $table */
                $table->bigInteger('client_id')->unsigned()->primary();
                $table->integer('window_start');
                $table->integer('hits')->default(0);
            });
        }

        return ['status' => 'success', 'description' => 'WHMCS-DNS addon activated.'];
    } catch (Throwable $e) {
        return ['status' => 'error', 'description' => 'Activation failed: ' . $e->getMessage()];
    }
}

/**
 * Deactivate: preserve data
 *
 * Tabelas zones/records sao preservadas para permitir reativacao sem perda
 * de mapeamento. A purga e uma acao explicita do admin (whmcs_dns_output).
 * Zonas no provedor externo NAO sao removidas ao desativar.
 */
function whmcs_dns_deactivate()
{
    return [
        'status' => 'success',
        'description' => 'Addon desativado. As tabelas zones/records foram preservadas para reativacao. '
            . 'As zonas criadas no provedor externo NAO foram removidas.',
    ];
}

function whmcs_dns_upgrade($vars)
{
    // Chamado pelo WHMCS quando a versao do addon sobe. Idempotente por design:
    // cada migracao verifica o estado antes de alterar.
    $version = (string) (whmcs_dns_config()['version'] ?? '');
    logActivity('WHMCS-DNS: addon atualizado para a versao ' . ($version !== '' ? $version : 'desconhecida'));
}

function whmcs_dns_output($vars)
{
    $purgeDone = false;

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string) ($_POST['whmcs_dns_purge'] ?? '') === '1') {
        check_token();

        if ((string) ($_POST['confirm_purge'] ?? '') !== 'yes') {
            echo '<div class="alert alert-warning">Purga cancelada: confirmacao ausente.</div>';
        } elseif (!Capsule::schema()->hasTable(WHMCSDNS_TABLE_ZONES) || !Capsule::schema()->hasTable(WHMCSDNS_TABLE_RECORDS)) {
            echo '<div class="alert alert-warning">Tabelas do addon nao existem. Nada a remover.</div>';
        } else {
            $zoneTotal = Capsule::table(WHMCSDNS_TABLE_ZONES)->count();
            $recordTotal = Capsule::table(WHMCSDNS_TABLE_RECORDS)->count();
            Capsule::table(WHMCSDNS_TABLE_RECORDS)->delete();
            Capsule::table(WHMCSDNS_TABLE_ZONES)->delete();
            logActivity("WHMCS-DNS: purga de dados locais executada (zonas={$zoneTotal}, registros={$recordTotal})");
            $purgeDone = true;
        }
    }

    if ($purgeDone) {
        echo '<div class="alert alert-success">Dados locais removidos. As zonas no provedor externo NAO foram removidas.</div>';
    }

    $zoneCount = Capsule::schema()->hasTable(WHMCSDNS_TABLE_ZONES)
        ? Capsule::table(WHMCSDNS_TABLE_ZONES)->count()
        : 0;
    $recordCount = Capsule::schema()->hasTable(WHMCSDNS_TABLE_RECORDS)
        ? Capsule::table(WHMCSDNS_TABLE_RECORDS)->count()
        : 0;

    echo '<div class="alert alert-info">
        Namingo DNS is configured from 
        <strong>Configuration → System Settings → Addon Modules</strong>.
        No additional administration is required here.
    </div>';

    echo '<div class="panel panel-default">
        <div class="panel-heading"><strong>Dados locais</strong></div>
        <div class="panel-body">
            <p>Zonas mapeadas: <strong>' . (int) $zoneCount . '</strong> · Registros: <strong>' . (int) $recordCount . '</strong></p>
            <p class="text-muted">Desativar o addon nao apaga estas tabelas e nao remove zonas no provedor externo.</p>
            <form method="post" onsubmit="return confirm(\'Isto apaga TODOS os mapeamentos locais (zonas e registros). As zonas no provedor externo permanecem. Continuar?\');">
                <input type="hidden" name="token" value="' . htmlspecialchars((function_exists('generate_token') ? (string) generate_token() : ''), ENT_QUOTES, 'UTF-8') . '" />
                <input type="hidden" name="whmcs_dns_purge" value="1" />
                <label style="font-weight:normal;">
                    <input type="checkbox" name="confirm_purge" value="yes" required /> Confirmo a remocao dos dados locais
                </label>
                <button type="submit" class="btn btn-danger">Remover todos os dados locais</button>
            </form>
        </div>
    </div>';
}

function whmcs_dns_can_delete_zone(string $domainName, array $zoneConfig): bool
{
    $provider = (string)($zoneConfig['provider'] ?? '');

    if ($provider === 'GandiLiveDNS') {
        return false;
    }

    if ($provider !== 'Scaleway') {
        return $provider !== '';
    }

    $zone = strtolower(rtrim(trim($domainName), '.'));
    $parent = strtolower(rtrim(trim((string)($zoneConfig['parent_domain'] ?? '')), '.'));

    if ($zone === '' || $parent === '') {
        // The persisted zone configuration is authoritative. Without a saved
        // parent, treat the Scaleway zone as an undeletable managed root/unknown
        // rather than guessing from today's global module settings.
        return false;
    }

    return $zone !== $parent && str_ends_with($zone, '.' . $parent);
}

function whmcs_dns_deletion_config(array $vars, string $domainName, array $zoneConfig): array
{
    $config = whmcs_dns_provider_config($vars, $domainName);
    $storedProvider = (string)($zoneConfig['provider'] ?? '');

    if ($storedProvider === '' || $storedProvider !== (string)($config['provider'] ?? '')) {
        throw new WhmcsDnsUserException('DNS provider settings have changed. Contact support.');
    }

    // Keep current credentials, but preserve the structural provider identity
    // used when this zone was provisioned.
    $config['provider'] = $storedProvider;

    if ($storedProvider === 'Scaleway') {
        foreach (['project_id', 'parent_domain'] as $key) {
            if (array_key_exists($key, $zoneConfig)) {
                $config[$key] = $zoneConfig[$key];
            }
        }
    }

    return $config;
}

function whmcs_dns_provider_config(array $vars, string $domainName): array
{
    $config = [
        'domain_name' => $domainName,
        'provider' => $vars['provider'] ?? '',
        'apikey' => $vars['apikey'] ?? '',
        'serverid' => isset($vars['anycast_server_id']) ? (int)$vars['anycast_server_id'] : 0,
        'cloudns_auth_id' => $vars['cloudns_auth_id'] ?? '',
        'cloudns_auth_password' => $vars['cloudns_auth_password'] ?? '',
        'project_id' => $vars['scaleway_project_id'] ?? '',
        'parent_domain' => $vars['scaleway_parent_domain'] ?? '',
        'sharing_id' => $vars['gandi_sharing_id'] ?? '',
        'auth_scheme' => $vars['gandi_auth_scheme'] ?? 'Bearer',
        'soa_email' => $vars['soa_email'] ?? '',
    ];
    if ($config['provider'] === 'PowerDNS') $config['powerdnsip'] = $vars['bind_powerdns_api_ip'] ?? '';
    if ($config['provider'] === 'Bind') $config['bindip'] = $vars['bind_powerdns_api_ip'] ?? '';
    for ($i = 1; $i <= 5; $i++) {
        if (!empty($vars['ns' . $i])) $config['ns' . $i] = $vars['ns' . $i];
    }
    return $config;
}

/**
/**
 * Status de dominio que autorizam gestao de DNS.
 *
 * Evidencia: lknclientdetailsmanager/ProfileCompleteness.php:18 trata
 * Active, Pending, Pending Transfer, Grace e Redemption como dominio vivo.
 * Aqui ficam de fora Pending e Pending Transfer: o dominio ainda nao esta
 * autoritativo na conta (registro/transferencia em andamento), e provisionar
 * zona consome cota do provedor. Ajustar esta lista e a unica mudanca
 * necessaria para adotar outro conjunto.
 */
function whmcs_dns_allowed_domain_statuses(): array
{
    return ['Active', 'Grace', 'Redemption'];
}

/**
 * Garante a tabela de throttle (instalacoes antigas nao rodam activate de novo).
 */
function whmcs_dns_ensure_throttle_table(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    if (Capsule::schema()->hasTable(WHMCSDNS_TABLE_THROTTLE)) {
        $ensured = true;
        return;
    }

    try {
        Capsule::schema()->create(WHMCSDNS_TABLE_THROTTLE, function ($table) {
            /** @var \Illuminate\Database\Schema\Blueprint $table */
            $table->bigInteger('client_id')->unsigned()->primary();
            $table->integer('window_start');
            $table->integer('hits')->default(0);
        });
    } catch (Throwable $e) {
        // Corrida entre dois requests: se a tabela existe agora, segue.
        if (!Capsule::schema()->hasTable(WHMCSDNS_TABLE_THROTTLE)) {
            throw $e;
        }
    }

    $ensured = true;
}

/**
 * Janela fixa por cliente. O incremento e condicional no SQL (hits < limite),
 * entao duas requisicoes concorrentes nao passam ambas no ultimo slot.
 * E trava antiabuso, nao fronteira de seguranca.
 */
function whmcs_dns_rate_ok(int $clientId, int $limit = 30, int $windowSeconds = 60): bool
{
    whmcs_dns_ensure_throttle_table();

    $table = WHMCSDNS_TABLE_THROTTLE;
    $now = time();
    $row = Capsule::table($table)->where('client_id', $clientId)->first();

    if (!$row || ($now - (int)$row->window_start) >= $windowSeconds) {
        Capsule::table($table)->updateOrInsert(
            ['client_id' => $clientId],
            ['window_start' => $now, 'hits' => 1]
        );
        return true;
    }

    $affected = (int) Capsule::connection()->update(
        'UPDATE ' . $table . ' SET hits = hits + 1 WHERE client_id = ? AND window_start = ? AND hits < ?',
        [$clientId, (int)$row->window_start, $limit]
    );

    return $affected === 1;
}

/**
 * Client area page
 */
function whmcs_dns_clientarea($vars)
{
    // Ensure client is logged in
    if (empty($_SESSION['uid'])) {
        return [
            'pagetitle'    => 'DNS Manager',
            'breadcrumb'   => ['index.php?m=whmcs_dns' => 'DNS Manager'],
            'templatefile' => 'clientarea',
            'requirelogin' => true,
            'vars'         => ['error' => 'Please login first.'],
        ];
    }

    $clientId = (int) $_SESSION['uid'];

    $provider = $vars['provider'] ?? '';
    $apikey   = $vars['apikey'] ?? '';
    $zoneLimit = max(0, (int)($vars['max_zones_per_client'] ?? 0));
    $zoneCount = Capsule::table(WHMCSDNS_TABLE_ZONES)->where('client_id', $clientId)->count();

    // List user WHMCS domains
    $clientDomains = Capsule::table('tbldomains')
        ->select('id', 'domain', 'status')
        ->where('userid', $clientId)
        ->orderBy('domain', 'asc')
        ->get()
        ->map(function ($d) {
            return [
                'id'     => (int)$d->id,
                'domain' => (string)$d->domain,
                'status' => (string)$d->status,
            ];
        })
        ->toArray();

    $selectedDomain = trim((string)($_REQUEST['domain'] ?? ''));
    $message = null;

    // Leitura: aceita dominio com status autorizado ou zona ja existente do cliente
    // (zona de dominio expirado continua visivel para poder ser desativada).
    if ($selectedDomain !== '') {
        $eligible = false;
        foreach ($clientDomains as $d) {
            if ($d['domain'] === $selectedDomain
                && in_array($d['status'], whmcs_dns_allowed_domain_statuses(), true)) {
                $eligible = true;
                break;
            }
        }
        if (!$eligible) {
            $hasZone = Capsule::table(WHMCSDNS_TABLE_ZONES)
                ->where('domain_name', $selectedDomain)
                ->where('client_id', $clientId)
                ->exists();
            if (!$hasZone) {
                $message = ['type' => 'error', 'text' => 'Domain does not exist or is not active.'];
                $selectedDomain = '';
            }
        }
    }

    $pdo = Capsule::connection()->getPdo();
    $cardo = new CardoService($pdo);

    // Helper: fetch zone
    $getZone = function (string $domainName) use ($clientId) {
        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
            ->where('domain_name', $domainName)
            ->where('client_id', $clientId)
            ->first();
        return $zone ?: null;
    };

    // Handle actions (add / update / delete)
    if (!empty($_POST['action'])) {
        check_token(); // WHMCS client token

        $action = (string)$_POST['action'];
        $domainName = trim((string)($_POST['domain_name'] ?? ''));
        if (!whmcs_dns_rate_ok($clientId)) {
            logActivity("WHMCS-DNS: throttle cliente={$clientId} acao={$action}");
            $message = ['type' => 'error', 'text' => 'Muitas operacoes em pouco tempo. Aguarde um minuto.'];
        } elseif ($domainName === '') {
            $message = ['type' => 'error', 'text' => 'Domain is required.'];
        } else {
            // Ownership check: must be in tbldomains for this user
            $ownerRow = Capsule::table('tbldomains')
                ->select('id', 'status')
                ->where('userid', $clientId)
                ->where('domain', $domainName)
                ->first();
            $ownsAny = $ownerRow !== null;
            $ownsActive = $ownsAny
                && in_array((string)$ownerRow->status, whmcs_dns_allowed_domain_statuses(), true);

            // Desativar zona existente continua permitido com status inativo
            // (o cliente precisa poder liberar a cota). Mutacoes nao.
            $disableOnly = $action === 'disable_dns' && $ownsAny
                && Capsule::table(WHMCSDNS_TABLE_ZONES)
                    ->where('domain_name', $domainName)
                    ->where('client_id', $clientId)
                    ->exists();

            // Dominio com outro titular ativo => nao permitir disputa silenciosa
            $otherOwner = $ownsAny && Capsule::table('tbldomains')
                ->where('domain', $domainName)
                ->where('userid', '<>', $clientId)
                ->whereIn('status', whmcs_dns_allowed_domain_statuses())
                ->exists();

            if (!$ownsAny) {
                $message = ['type' => 'error', 'text' => 'Domain does not exist.'];
            } elseif (!$ownsActive && !$disableOnly) {
                $message = ['type' => 'error', 'text' => 'Domain does not exist or is not active.'];
            } elseif ($otherOwner && !$disableOnly) {
                logActivity("WHMCS-DNS: conflito de titularidade dominio={$domainName} cliente={$clientId}");
                $message = ['type' => 'error', 'text' => 'Este dominio esta em revisao de titularidade. Contate o suporte.'];
            } elseif ($provider === '') {
                $message = ['type' => 'error', 'text' => 'DNS provider is not configured.'];
            } else {
                try {
                    if ($action === 'enable_dns') {
                        // Create zone explicitly (no silent auto-create)
                        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
                            ->where('domain_name', $domainName)
                            ->where('client_id', $clientId)
                            ->first();

                        if ($zone) {
                            $message = ['type' => 'success', 'text' => 'DNS is already enabled for this domain.'];
                        } else {
                            if (Capsule::table(WHMCSDNS_TABLE_ZONES)->where('domain_name', $domainName)->exists()) {
                                throw new WhmcsDnsUserException('This DNS zone is already managed by another client.');
                            }
                            $cfg = whmcs_dns_provider_config($vars, $domainName);

                            $domainOrder = [
                                'client_id' => $clientId,
                                'config'    => json_encode($cfg, JSON_UNESCAPED_SLASHES),
                            ];
                            $storedConfig = json_encode(
                                ['provider' => (string)($cfg['provider'] ?? '')],
                                JSON_UNESCAPED_SLASHES
                            );

                            // A2: reserva a linha na mesma transacao do count. A leitura
                            // FOR UPDATE serializa por cliente (inclusive gap lock com 0 zonas).
                            Capsule::connection()->transaction(function () use ($clientId, $zoneLimit, $domainName, $storedConfig) {
                                $ids = Capsule::connection()->select(
                                    'SELECT id FROM ' . WHMCSDNS_TABLE_ZONES . ' WHERE client_id = ? FOR UPDATE',
                                    [$clientId]
                                );
                                if ($zoneLimit > 0 && count($ids) >= $zoneLimit) {
                                    throw new WhmcsDnsUserException('DNS zone limit reached. Disable a zone or contact support.');
                                }
                                Capsule::table(WHMCSDNS_TABLE_ZONES)->insert([
                                    'client_id'   => $clientId,
                                    'domain_name' => $domainName,
                                    'config'      => $storedConfig,
                                    'created_at'  => date('Y-m-d H:i:s'),
                                    'updated_at'  => date('Y-m-d H:i:s'),
                                ]);
                            });

                            try {
                                $cardo->createDomain($domainOrder);
                            } catch (Throwable $e) {
                                // Nao deixa zona fantasma local se o provider falhou.
                                Capsule::table(WHMCSDNS_TABLE_ZONES)
                                    ->where('domain_name', $domainName)
                                    ->where('client_id', $clientId)
                                    ->whereNull('zoneId')
                                    ->delete();
                                throw $e;
                            }

                            // Ensure local row exists if Cardo DNS didn't insert it itself
                            $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)->where('domain_name', $domainName)->first();
                            if (!$zone) {
                                Capsule::table(WHMCSDNS_TABLE_ZONES)->insert([
                                    'client_id'   => $clientId,
                                    'domain_name' => $domainName,
                                    'config'      => $storedConfig,
                                    'created_at'  => date('Y-m-d H:i:s'),
                                    'updated_at'  => date('Y-m-d H:i:s'),
                                ]);
                            }

                            $message = ['type' => 'success', 'text' => 'DNS enabled. Zone created.'];
                            $zoneCount++;
                        }
                    }

                    if ($action === 'disable_dns') {
                        // Delete zone explicitly
                        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
                            ->where('domain_name', $domainName)
                            ->where('client_id', $clientId)
                            ->first();

                        if (!$zone) {
                            $message = ['type' => 'success', 'text' => 'DNS is already disabled (zone not found).'];
                        } else {
                            $zoneConfig = json_decode((string)$zone->config, true);
                            if (!is_array($zoneConfig)) {
                                throw new WhmcsDnsUserException('Stored DNS zone configuration is invalid.');
                            }

                            if (!whmcs_dns_can_delete_zone($domainName, $zoneConfig)) {
                                throw new WhmcsDnsUserException(
                                    'This DNS zone cannot be deleted through Cardo DNS. '
                                    . 'Gandi LiveDNS does not expose zone removal, and Scaleway managed root zones '
                                    . 'cannot be deleted independently. The zone has not been removed locally or remotely.'
                                );
                            }

                            $cfg = whmcs_dns_deletion_config($vars, $domainName, $zoneConfig);

                            $cardo->deleteDomain([
                                'config' => json_encode($cfg, JSON_UNESCAPED_SLASHES),
                            ]);

                            Capsule::table(WHMCSDNS_TABLE_RECORDS)->where('domain_id', $zone->id)->delete();
                            Capsule::table(WHMCSDNS_TABLE_ZONES)->where('id', $zone->id)->delete();

                            $message = ['type' => 'success', 'text' => 'DNS disabled. Zone deleted.'];
                            $zoneCount--;
                        }
                    }

                    if ($action === 'add_record') {
                        $recordName = strtolower(trim((string)($_POST['record_name'] ?? '')));
                        if ($recordName === '@' || rtrim($recordName, '.') === $domainName) {
                            $recordName = '';
                        }
                        if (str_ends_with($recordName, '.' . $domainName . '.')) {
                            $recordName = substr($recordName, 0, -strlen($domainName) - 2);
                        }
                        if (str_ends_with($recordName, '.' . $domainName)) {
                            $recordName = substr($recordName, 0, -strlen($domainName) - 1);
                        }
                        $recordType  = strtoupper((string)($_POST['record_type'] ?? ''));
                        $recordValue = (string)($_POST['record_value'] ?? '');
                        $ttl         = isset($_POST['record_ttl']) ? (int)$_POST['record_ttl'] : 3600;
                        $priority    = (isset($_POST['record_priority']) && $_POST['record_priority'] !== '')
                            ? (int)$_POST['record_priority'] : null;

                        // Bunny nao expoe o tipo SPF (TYPE_MAP); SPF moderno e TXT (v=spf1 ...).
                        if ($recordType === 'SPF' && $provider === 'Bunny') {
                            $recordType = 'TXT';
                        }

                        if ($recordType === '' || $recordValue === '') {
                            throw new WhmcsDnsUserException('Record type and value are required.');
                        }

                        if ($recordType === 'MX' && $priority === null) {
                            $priority = 0;
                        }

                        if ($recordType === 'TXT') {
                            $v = trim($recordValue);
                            if ($v === '' || $v[0] !== '"' || substr($v, -1) !== '"') {
                                $recordValue = '"' . str_replace('"', '\"', $v) . '"';
                            }
                        }

                        if (in_array($provider, ['PowerDNS'], true) && $recordType === 'CNAME') {
                            $recordValue = rtrim(trim($recordValue), '.') . '.';
                        }

                        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
                            ->where('domain_name', $domainName)
                            ->where('client_id', $clientId)
                            ->first();
                        if (!$zone) {
                            throw new WhmcsDnsUserException('DNS is not enabled for this domain. Click "Enable DNS" first.');
                        }

                        $maxRecords = max(0, (int)($vars['max_records_per_zone'] ?? 0));
                        if ($maxRecords > 0) {
                            $recordCount = Capsule::table(WHMCSDNS_TABLE_RECORDS)
                                ->where('domain_id', $zone->id)
                                ->count();
                            if ($recordCount >= $maxRecords) {
                                throw new WhmcsDnsUserException('Limite de registros desta zona atingido. Remova um registro ou contate o suporte.');
                            }
                        }

                        $req = [
                            'domain_name'      => $domainName,
                            'record_name'      => $recordName,
                            'record_type'      => $recordType,
                            'record_value'     => $recordValue,
                            'record_ttl'       => $ttl,
                            'record_priority'  => $priority,
                            'provider'         => $provider,
                            'apikey'           => $apikey,
                        ];

                        if ($provider === 'PowerDNS') {
                            $req['powerdnsip'] = $vars['bind_powerdns_api_ip'] ?? null;
                            for ($i = 1; $i <= 5; $i++) {
                                $k = 'ns' . $i;
                                if (!empty($vars[$k])) $req[$k] = $vars[$k];
                            }
                        } elseif ($provider === 'Bind') {
                            $req['bindip'] = $vars['bind_powerdns_api_ip'] ?? null;
                            for ($i = 1; $i <= 5; $i++) {
                                $k = 'ns' . $i;
                                if (!empty($vars[$k])) $req[$k] = $vars[$k];
                            }
                        }

                        $cardo->addRecord(array_merge(whmcs_dns_provider_config($vars, $domainName), $req));

                        $message = ['type' => 'success', 'text' => 'Record added.'];
                    }

                    if ($action === 'update_record') {
                        $rowId      = (int)($_POST['row_id'] ?? 0);
                        $recordType  = strtoupper((string)($_POST['record_type'] ?? ''));
                        $recordValue = (string)($_POST['record_value'] ?? '');
                        $ttl         = isset($_POST['record_ttl']) ? (int)$_POST['record_ttl'] : 3600;
                        $priority    = (isset($_POST['record_priority']) && $_POST['record_priority'] !== '')
                            ? (int)$_POST['record_priority'] : null;

                        if ($rowId <= 0) {
                            throw new WhmcsDnsUserException('Invalid record row id.');
                        }
                        
                        if ($recordType === 'MX' && $priority === null) {
                            $priority = 0;
                        }

                        if ($recordType === 'TXT') {
                            $v = trim($recordValue);
                            if ($v === '' || $v[0] !== '"' || substr($v, -1) !== '"') {
                                $recordValue = '"' . str_replace('"', '\"', $v) . '"';
                            }
                        }

                        if (in_array($provider, ['PowerDNS'], true) && $recordType === 'CNAME') {
                            $recordValue = rtrim(trim($recordValue), '.') . '.';
                        }

                        // Resolve zone + row ownership
                        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
                            ->where('domain_name', $domainName)
                            ->where('client_id', $clientId)
                            ->first();
                        if (!$zone) {
                            throw new WhmcsDnsUserException('Zone not found. Refresh and try again.');
                        }

                        $rec = Capsule::table(WHMCSDNS_TABLE_RECORDS)
                            ->where('id', $rowId)
                            ->where('domain_id', $zone->id)
                            ->first();
                        if (!$rec) {
                            throw new WhmcsDnsUserException('Record not found. Please refresh and try again.');
                        }

                        $oldValue = (string)($_POST['old_value'] ?? '');
                        if ($oldValue !== '' && (string)$rec->value !== $oldValue) {
                            throw new WhmcsDnsUserException('Record changed since page load. Please refresh and try again.');
                        }

                        // O tipo autoritativo e o da linha local; SPF legado no Bunny vira TXT.
                        $recordType = strtoupper((string)$rec->type);
                        if ($recordType === 'SPF' && $provider === 'Bunny') {
                            $recordType = 'TXT';
                        }
                        if ($recordType === 'TXT') {
                            $v = trim($recordValue);
                            if ($v === '' || $v[0] !== '"' || substr($v, -1) !== '"') {
                                $recordValue = '"' . str_replace('"', '\\"', $v) . '"';
                            }
                        }

                        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
                            ->where('domain_name', $domainName)
                            ->where('client_id', $clientId)
                            ->first();
                        if (!$zone) {
                            throw new WhmcsDnsUserException('DNS is not enabled for this domain. Click "Enable DNS" first.');
                        }

                        $recordName = strtolower(trim((string)$rec->host));
                        if ($recordName === '@' || rtrim($recordName, '.') === $domainName) {
                            $recordName = '';
                        }
                        if (str_ends_with($recordName, '.' . $domainName . '.')) {
                            $recordName = substr($recordName, 0, -strlen($domainName) - 2);
                        }
                        if (str_ends_with($recordName, '.' . $domainName)) {
                            $recordName = substr($recordName, 0, -strlen($domainName) - 1);
                        }

                        $req = [
                            'domain_name'      => $domainName,
                            'record_id'        => $rowId,
                            'record_name'      => $recordName,
                            'record_type'      => $recordType,
                            'record_value'     => $recordValue,
                            'old_value'        => (string)$rec->value,
                            'record_ttl'       => $ttl,
                            'record_priority'  => $priority,
                            'provider'         => $provider,
                            'apikey'           => $apikey,
                        ];

                        if ($provider === 'PowerDNS') {
                            $req['powerdnsip'] = $vars['bind_powerdns_api_ip'] ?? null;
                            for ($i = 1; $i <= 5; $i++) {
                                $k = 'ns' . $i;
                                if (!empty($vars[$k])) $req[$k] = $vars[$k];
                            }
                        } elseif ($provider === 'Bind') {
                            $req['bindip'] = $vars['bind_powerdns_api_ip'] ?? null;
                            for ($i = 1; $i <= 5; $i++) {
                                $k = 'ns' . $i;
                                if (!empty($vars[$k])) $req[$k] = $vars[$k];
                            }
                        }

                        $cardo->updateRecord(array_merge(whmcs_dns_provider_config($vars, $domainName), $req));

                        // Mantem a linha local coerente quando o tipo gravado era SPF (Bunny).
                        if ($recordType !== strtoupper((string)$rec->type)) {
                            Capsule::table(WHMCSDNS_TABLE_RECORDS)
                                ->where('id', $rowId)
                                ->where('domain_id', $zone->id)
                                ->update(['type' => $recordType]);
                        }

                        $message = ['type' => 'success', 'text' => 'Record updated.'];
                    }

                    if ($action === 'delete_record') {
                        $rowId = (int)($_POST['row_id'] ?? 0);
                        if ($rowId <= 0) {
                            throw new WhmcsDnsUserException('Invalid record row id.');
                        }

                        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
                            ->where('domain_name', $domainName)
                            ->where('client_id', $clientId)
                            ->first();
                        if (!$zone) {
                            throw new WhmcsDnsUserException('Zone not found. Refresh and try again.');
                        }

                        $rec = Capsule::table(WHMCSDNS_TABLE_RECORDS)
                            ->where('id', $rowId)
                            ->where('domain_id', $zone->id)
                            ->first();
                        if (!$rec) {
                            throw new WhmcsDnsUserException('Record not found. Please refresh and try again.');
                        }

                        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
                            ->where('domain_name', $domainName)
                            ->where('client_id', $clientId)
                            ->first();
                        if (!$zone) {
                            throw new WhmcsDnsUserException('DNS is not enabled for this domain. Click "Enable DNS" first.');
                        }

                        $req = [
                            'domain_name'      => $domainName,
                            'record_id'        => $rowId,
                            'record_name'      => (string)$rec->host,
                            'record_type'      => strtoupper((string)$rec->type),
                            'record_value'     => (string)$rec->value,
                            'provider'         => $provider,
                            'apikey'           => $apikey,
                        ];

                        if ($provider === 'PowerDNS') {
                            $req['powerdnsip'] = $vars['bind_powerdns_api_ip'] ?? null;
                            for ($i = 1; $i <= 5; $i++) {
                                $k = 'ns' . $i;
                                if (!empty($vars[$k])) $req[$k] = $vars[$k];
                            }
                        } elseif ($provider === 'Bind') {
                            $req['bindip'] = $vars['bind_powerdns_api_ip'] ?? null;
                            for ($i = 1; $i <= 5; $i++) {
                                $k = 'ns' . $i;
                                if (!empty($vars[$k])) $req[$k] = $vars[$k];
                            }
                        }

                        $cardo->delRecord(array_merge(whmcs_dns_provider_config($vars, $domainName), $req));

                        $message = ['type' => 'success', 'text' => 'Record deleted.'];
                    }
                    if ($action === 'enable_dnssec' || $action === 'disable_dnssec') {
                        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
                            ->where('domain_name', $domainName)->where('client_id', $clientId)->first();
                        if (!$zone) throw new WhmcsDnsUserException('Enable DNS for this domain first.');
                        $config = whmcs_dns_provider_config($vars, $domainName);
                        if ((json_decode((string)$zone->config, true)['provider'] ?? '') !== $config['provider']) {
                            throw new WhmcsDnsUserException('DNS provider settings have changed. Contact support.');
                        }
                        $capabilities = $cardo->getDNSSECCapabilities($config);
                        $operation = $action === 'enable_dnssec' ? 'can_enable' : 'can_disable';
                        if (!$capabilities['supported'] || !$capabilities[$operation]) {
                            throw new WhmcsDnsUserException('This provider does not support that DNSSEC action.');
                        }
                        if ($action === 'enable_dnssec') $cardo->enableDNSSEC($config);
                        else $cardo->disableDNSSEC($config);
                        $message = ['type' => 'success', 'text' => 'DNSSEC updated.'];
                    }
                } catch (Throwable $e) {
                    $message = whmcs_dns_error_message($e, [
                        'domain' => $domainName,
                        'client_id' => $clientId,
                        'action' => $action,
                    ]);
                }
            }
        }

        // keep domain selected after POST
        $selectedDomain = $selectedDomain ?: $domainName;
    }

    // Fetch zone + records for selected domain
    $zoneData = null;
    $records = [];
    $nameservers = [];
    $dnssec = null;

    if ($selectedDomain !== '') {
        $zone = Capsule::table(WHMCSDNS_TABLE_ZONES)
            ->where('domain_name', $selectedDomain)
            ->where('client_id', $clientId)
            ->first();

        if ($zone) {
            $persistedZoneConfig = json_decode((string)$zone->config, true);
            if (!is_array($persistedZoneConfig)) {
                $persistedZoneConfig = [];
            }

            $zoneData = [
                'id'          => (int)$zone->id,
                'domain_name' => (string)$zone->domain_name,
                'created_at'  => (string)$zone->created_at,
                'updated_at'  => (string)$zone->updated_at,
                'config'      => $persistedZoneConfig,
            ];

            $records = Capsule::table(WHMCSDNS_TABLE_RECORDS)
                ->select('id', 'type', 'host', 'value', 'ttl', 'priority', 'recordId')
                ->where('domain_id', $zone->id)
                ->orderBy('type', 'asc')
                ->orderBy('host', 'asc')
                ->get()
                ->map(function ($r) {
                    return [
                        'id'       => (int)$r->id,
                        'type'     => (string)$r->type,
                        'host'     => (string)$r->host,
                        'value'    => (string)$r->value,
                        'ttl'      => $r->ttl !== null ? (int)$r->ttl : null,
                        'priority' => $r->priority !== null ? (int)$r->priority : null,
                        'recordId' => (string)($r->recordId ?? ''),
                    ];
                })
                ->toArray();

            $config = whmcs_dns_provider_config($vars, $selectedDomain);
            for ($i = 1; $i <= 5; $i++) {
                if (!empty($config['ns' . $i])) $nameservers[] = trim((string)$config['ns' . $i]);
            }
            if ($provider === 'Cloudflare') {
                // Cloudflare assigns nameservers per zone, unlike the global NS settings.
                $nameservers = [];
                try {
                    $details = (new \Namingo\Cardo\DNS\Providers\Cloudflare($config))->getDomain($selectedDomain);
                    $nameservers = array_values(array_filter($details['name_servers'] ?? [], 'is_string'));
                } catch (Throwable $e) {
                    $message = whmcs_dns_error_message($e, [
                        'domain' => $selectedDomain,
                        'client_id' => $clientId,
                        'action' => 'nameservers',
                    ]);
                }
            }
            try {
                if ((json_decode((string)$zone->config, true)['provider'] ?? '') !== $provider) {
                    throw new WhmcsDnsUserException('DNS provider settings have changed. Contact support.');
                }
                $capabilities = $cardo->getDNSSECCapabilities($config);
                if ($capabilities['supported']) {
                    $status = $cardo->getDNSSECStatus($config);
                    $ds = $status['ds'] ?? null;
                    if (($status['enabled'] ?? $capabilities['enforced']) && ($ds === null || $ds === [])) {
                        $ds = $cardo->getDSRecords($config);
                    }
                    if (!is_array($ds)) $ds = $ds ? [$ds] : [];
                    elseif ($ds && !array_is_list($ds)) $ds = [$ds];
                    $dnssec = array_merge($capabilities, [
                        'enabled' => (bool)($status['enabled'] ?? $capabilities['enforced']),
                        'ds' => array_map(static function ($record) {
                            if (is_string($record)) return $record;
                            if (!is_array($record)) return '';
                            $parts = [
                                $record['key_tag'] ?? $record['keytag'] ?? $record['keyTag'] ?? '',
                                $record['algorithm'] ?? '',
                                $record['digest_type'] ?? $record['digestType'] ?? '',
                                $record['digest'] ?? '',
                            ];
                            return in_array('', $parts, true) ? json_encode($record, JSON_UNESCAPED_SLASHES) : implode(' ', $parts);
                        }, $ds),
                    ]);
                }
            } catch (Throwable $e) {
                $message = whmcs_dns_error_message($e, [
                    'domain' => $selectedDomain,
                    'client_id' => $clientId,
                    'action' => 'dnssec_status',
                ]);
            }
        }
    }

    $domainCrumbs = [
        'index.php?m=whmcs_dns&domain=' . urlencode($selectedDomain) => 'DNS Manager',
    ];

    if ($selectedDomain !== '') {
        $domainId = (int) Capsule::table('tbldomains')
            ->where('userid', $clientId)
            ->where('domain', $selectedDomain)
            ->value('id');

        if ($domainId > 0) {
            $domainCrumbs = [
                'clientarea.php?action=domains' => 'My Domains',
                'clientarea.php?action=domaindetails&id=' . $domainId => $selectedDomain,
                'index.php?m=whmcs_dns&domain=' . urlencode($selectedDomain) => 'DNS Manager',
            ];
        }
    }

    return [
        'pagetitle'    => 'DNS Manager',
        'breadcrumb' => $domainCrumbs,
        'templatefile' => 'clientarea',
        'requirelogin' => true,
        'vars'         => [
            'message'        => $message,
            'token'          => function_exists('generate_token') ? (string) generate_token() : (string) ($GLOBALS['token'] ?? ''),
            'clientDomains'  => $clientDomains,
            'selectedDomain' => $selectedDomain,
            'zone'           => $zoneData,
            'records'        => $records,
            'nameservers'    => $nameservers,
            'dnssec'         => $dnssec,
            'zoneLimit'      => $zoneLimit,
            'zoneCount'      => $zoneCount,
            'provider'       => $provider,
            'canDisableZone' => $zoneData !== null
                ? whmcs_dns_can_delete_zone($selectedDomain, $zoneData['config'] ?? [])
                : false,
        ],
    ];
}
