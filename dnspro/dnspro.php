<?php
/**
 * DNS Pro - modulo de servidor WHMCS (Link Nacional)
 *
 * Provisiona a zona no provedor via Cardo (addon whmcs_dns) e aponta o
 * dominio para os nameservers do DNS Pro quando o registrar permite.
 * Sem servidor dedicado (RequiresServer = false).
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

function dnspro_MetaData()
{
    return [
        'DisplayName' => 'DNS Pro (Link Nacional)',
        'APIVersion' => '1.1',
        'RequiresServer' => false,
    ];
}

function dnspro_ConfigOptions()
{
    return [
        'max_records' => [
            'FriendlyName' => 'Máximo de registros por zona',
            'Type' => 'text',
            'Size' => '8',
            'Default' => '100',
            'Description' => '0 = ilimitado',
        ],
        'parking_ip' => [
            'FriendlyName' => 'IP de parking (suspensão)',
            'Type' => 'text',
            'Size' => '20',
            'Default' => '',
            'Description' => 'Reservado para a suspensão com página de serviço pausado. Vazio = apenas bloqueia edição.',
        ],
    ];
}

function dnspro_load_lib()
{
    $lib = __DIR__ . '/../../addons/whmcs_dns/lib/service.php';
    if (!file_exists($lib)) {
        return false;
    }
    require_once $lib;
    return true;
}

function dnspro_CreateAccount(array $params)
{
    if (!dnspro_load_lib()) {
        return 'Biblioteca do addon whmcs_dns não encontrada. Ative o addon DNS.';
    }

    $domain = (string) ($params['domain'] ?? '');
    $clientId = (int) ($params['clientsdetails']['userid'] ?? 0);
    $serviceId = (int) ($params['serviceid'] ?? 0);

    if ($domain === '' || $clientId <= 0) {
        return 'Domínio ou cliente ausente no pedido.';
    }

    $res = whmcs_dnspro_create_zone($domain, $clientId, $serviceId);
    if (!$res['ok']) {
        return 'Falha ao criar a zona: ' . (string) $res['error'];
    }

    return 'success';
}

function dnspro_SuspendAccount(array $params)
{
    if (!dnspro_load_lib()) {
        return 'Biblioteca do addon whmcs_dns não encontrada.';
    }

    $domain = (string) ($params['domain'] ?? '');
    $clientId = (int) ($params['clientsdetails']['userid'] ?? 0);
    whmcs_dnspro_set_state($domain, $clientId, 'suspended');
    logActivity("WHMCS-DNS Pro: servico suspenso dominio={$domain} cliente={$clientId}");
    return 'success';
}

function dnspro_UnsuspendAccount(array $params)
{
    if (!dnspro_load_lib()) {
        return 'Biblioteca do addon whmcs_dns não encontrada.';
    }

    $domain = (string) ($params['domain'] ?? '');
    $clientId = (int) ($params['clientsdetails']['userid'] ?? 0);
    whmcs_dnspro_set_state($domain, $clientId, 'active');
    logActivity("WHMCS-DNS Pro: servico reativado dominio={$domain} cliente={$clientId}");
    return 'success';
}

function dnspro_TerminateAccount(array $params)
{
    if (!dnspro_load_lib()) {
        return 'Biblioteca do addon whmcs_dns não encontrada.';
    }

    $domain = (string) ($params['domain'] ?? '');
    $clientId = (int) ($params['clientsdetails']['userid'] ?? 0);
    $res = whmcs_dnspro_delete_zone($domain, $clientId);
    if (!$res['ok']) {
        return 'Falha ao remover a zona: ' . (string) $res['error'];
    }

    return 'success';
}

function dnspro_AdminServicesTabFields(array $params)
{
    if (!dnspro_load_lib()) {
        return [];
    }

    $domain = (string) ($params['domain'] ?? '');
    $zone = \WHMCS\Database\Capsule::table('zones')->where('domain_name', $domain)->first();

    return [
        'Zona DNS' => $zone ? ('#' . (int) $zone->id . ' (provider: ' . (string) json_decode((string) $zone->config, true)['provider'] . ')') : 'não criada',
    ];
}
