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
            'order' => 100,
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
        $panel->addChild('LknDnsReglock', ['label' => 'Trava de Registro', 'uri' => $base . '#tabReglock', 'order' => 3]);
        $panel->addChild('LknDnsNameservers', [
            'label' => 'DNS (Nomes Servidores)',
            'uri' => 'index.php?m=whmcs_dns&view=nameservers&domain=' . urlencode((string) $domain->domain),
            'order' => 100,
            'current' => true,
        ]);
    }
});

/**
 * Aba nativa de nameservers: esconde a opcao "DNS padrao", garante "DNS
 * personalizado" marcado e coloca um botao Cancelar que volta para a nossa tela.
 */
add_hook('ClientAreaFooterOutput', 1, function () {
    if ((string) ($_GET['action'] ?? '') !== 'domaindetails' || empty($_SESSION['uid'])) {
        return '';
    }
    $domainId = (int) ($_GET['id'] ?? 0);
    if ($domainId <= 0) {
        return '';
    }
    $domain = Capsule::table('tbldomains')
        ->select('domain')
        ->where('id', $domainId)
        ->where('userid', (int) $_SESSION['uid'])
        ->first();
    if (!$domain || empty($domain->domain)) {
        return '';
    }

    $url = 'index.php?m=whmcs_dns&view=nameservers&domain=' . urlencode((string) $domain->domain);
    $urlJs = json_encode($url);

    return <<<HTML
<script>
(function () {
    var form = document.querySelector('#tabNameservers form');
    if (!form) { return; }
    var def = form.querySelector('input[name="nschoice"][value="default"]');
    if (def) {
        var wrap = def.closest('.form-check');
        if (wrap) { wrap.style.display = 'none'; }
    }
    var custom = form.querySelector('input[name="nschoice"][value="custom"]');
    if (custom) { custom.checked = true; }
    var submit = form.querySelector('button[type="submit"], input[type="submit"]');
    if (submit && !form.querySelector('.lkn-dns-cancel')) {
        var a = document.createElement('a');
        a.className = 'btn btn-default lkn-dns-cancel';
        a.style.marginRight = '.5rem';
        a.href = $urlJs;
        a.textContent = 'Cancelar';
        submit.parentNode.insertBefore(a, submit);
    }
})();
</script>
HTML;
});
