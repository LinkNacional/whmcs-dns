<?php
/**
 * WHMCS-DNS module
 *
 * Written in 2025 by Taras Kondratyuk (https://namingo.org)
 *
 * @license MIT
 * @see https://opensource.org/licenses/MIT
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;



/**
 * Substitui o item nativo "Nome de servidores de DNS (Nameservers)" pela
 * nossa tela de servicos (cards) na pagina de detalhes do dominio.
 */
add_hook('ClientAreaPrimarySidebar', 1, function ($sidebar) {
    if (empty($_SESSION['uid'])) {
        return;
    }
    if ((string) ($_GET['action'] ?? '') !== 'domaindetails') {
        return;
    }

    $domainId = (int) ($_GET['id'] ?? $_GET['domainid'] ?? 0);
    if ($domainId <= 0) {
        return;
    }

    $clientId = (int) $_SESSION['uid'];
    $domain = Capsule::table('tbldomains')
        ->select('domain')
        ->where('id', $domainId)
        ->where('userid', $clientId)
        ->first();

    if (!$domain || empty($domain->domain)) {
        return;
    }

    $panel = $sidebar->getChild('Domain Details Management');
    if (!$panel) {
        return;
    }

    $panel->removeChild('Modify Nameservers');

    if (!$panel->getChild('LknDnsNameservers')) {
        $panel->addChild('LknDnsNameservers', [
            'label' => 'DNS (Nomes Servidores)',
            'uri' => 'index.php?m=whmcs_dns&view=nameservers&domain=' . urlencode((string) $domain->domain),
            'order' => 3,
        ]);
    }
});

/** Sidebar na pagina DNS (Nomes Servidores): mantem o painel de navegacao do dominio. */
add_hook('ClientAreaPrimarySidebar', 2, function ($sidebar) {
    if ((string) ($_GET['view'] ?? '') !== 'nameservers') {
        return;
    }
    if (empty($_SESSION['uid'])) {
        return;
    }

    $clientId = (int) $_SESSION['uid'];
    $domainName = trim((string) ($_GET['domain'] ?? ''));
    $domain = Capsule::table('tbldomains')
        ->select('id', 'domain')
        ->where('userid', $clientId)
        ->where('domain', $domainName)
        ->first();

    if (!$domain) {
        return;
    }

    $panel = $sidebar->getChild('LknDnsManage');
    if (!$panel) {
        $panel = $sidebar->addChild('LknDnsManage', ['label' => 'Gerenciar', 'order' => 10]);
    }

    $base = 'clientarea.php?action=domaindetails&id=' . (int) $domain->id;
    if (!$panel->getChild('LknDnsOverview')) {
        $panel->addChild('LknDnsOverview', ['label' => 'Visão geral', 'uri' => $base . '#tabOverview', 'order' => 1]);
        $panel->addChild('LknDnsAutorenew', ['label' => 'Renovação Automática', 'uri' => $base . '#tabAutorenew', 'order' => 2]);
        $panel->addChild('LknDnsNameservers', [
            'label' => 'DNS (Nomes Servidores)',
            'uri' => 'index.php?m=whmcs_dns&view=nameservers&domain=' . urlencode((string) $domain->domain),
            'order' => 3,
            'current' => true,
        ]);
    }
});
