{if $error}
    <div class="alert alert-warning mb-3">{$error|escape}</div>
{else}
<h2 class="mb-1">DNS do seu domínio</h2>
<p class="text-muted mb-4">Escolha como deseja gerenciar os servidores de DNS do domínio <strong>{$domain|escape}</strong>.</p>

{if $notice}
    <div class="alert alert-{if $notice.type == 'success'}success{else}danger{/if} mb-4">{$notice.text|escape}</div>
{/if}

{if !$nsOk}
    <div class="alert alert-warning mb-4">
        {if $nsCached}
            Não foi possível ler os nameservers agora ({$nsError|escape}); exibindo a última leitura conhecida.
        {else}
            Não foi possível ler os nameservers agora ({$nsError|escape}). Os status abaixo podem estar incompletos — fale com o suporte se persistir.
        {/if}
    </div>
{/if}

<div class="card mb-4" style="border:1px solid #c7d2fe;">
    <div class="card-body d-flex align-items-center" style="gap:1rem;">
        <div style="font-size:1.6rem;color:#3b5bfd;"><i class="fas fa-network-wired"></i></div>
        <div class="flex-grow-1">
            <div class="h5 mb-1">
                {if $mode == 'dnspro'}DNS Pro
                {elseif $mode == 'cpanel'}cPanel
                {elseif $mode == 'directadmin'}DirectAdmin
                {elseif $mode == 'custom'}DNS Personalizado
                {elseif $mode == 'default'}DNS Padrão
                {else}Status indisponível
                {/if}
                {if $mode != 'unknown'}<span class="badge badge-success" style="margin-left:.5rem;">ATIVO</span>{/if}
            </div>
            <div class="small text-muted">
                Nameservers atuais:
                {if $nsText}{$nsText|escape}{else}—{/if}
            </div>
        </div>
        {if $mode == 'custom'}
            <a class="btn btn-outline-primary" href="{$nativeNsUrl|escape}">Configurar</a>
        {/if}
    </div>
</div>

<h3 class="h5 mb-3">Serviços disponíveis para domínios</h3>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-start mb-2">
                    <div class="me-3" style="font-size:1.4rem;color:#3b5bfd;"><i class="fas fa-globe"></i></div>
                    <div class="flex-grow-1" style="padding: 8px;">
                        <div class="h6 mb-1">DNS Padrão
                            {if $mode == 'default'}
                                <span class="badge badge-success" style="margin-left:.25rem;">ATIVO</span>
                            {elseif $nsOk || $nsCached}
                                <span class="badge badge-info" style="margin-left:.25rem;">Disponível</span>
                            {else}
                                <span class="badge badge-secondary" style="margin-left:.25rem;">Indisponível</span>
                            {/if}
                        </div>
                        <div class="small text-muted">Utilize nossos servidores DNS para gerenciar seu domínio de forma automática.</div>
                        <ul class="small text-muted mb-3" style="list-style:disc;padding-left:1.25rem;padding-top:0.88rem;">
                            {foreach $defaultNs as $nsItem}
                                <li>{$nsItem|escape}</li>
                            {/foreach}
                        </ul>
                    </div>
                </div>
                <div class="mt-auto">
                    {if $mode != 'default' && ($nsOk || $nsCached) && $domainStatus == 'Active'}
                        <form method="post" class="m-0" onsubmit="return confirm('Aplicar os nameservers padrão neste domínio?');">
                            <input type="hidden" name="token" value="{$token}" />
                            <input type="hidden" name="dnspro_action" value="apply_default" />
                            <button type="submit" class="btn btn-primary btn-sm">Ativar DNS Padrão</button>
                        </form>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-start mb-2">
                    <div class="me-3" style="font-size:1.4rem;color:#6f42c1;"><i class="fas fa-cloud"></i></div>
                    <div class="flex-grow-1" style="padding: 8px;">
                        <div class="h6 mb-1">DNS Personalizado
                            {if $mode == 'custom'}
                                <span class="badge badge-success" style="margin-left:.25rem;">ATIVO</span>
                            {/if}
                        </div>
                        <div class="small text-muted">Use os nameservers da sua hospedagem ou do seu provedor de DNS.</div>
                        <ul class="small text-muted mb-3" style="list-style:disc;padding-left:1.25rem;padding-top:0.88rem;">
                            {foreach $ns as $nsItem}
                                <li>{$nsItem|escape}</li>
                            {/foreach}
                        </ul>
                    </div>
                </div>
                <div class="mt-auto">
                    <a class="btn btn-primary" href="{$nativeNsUrl|escape}">Configurar</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-start mb-2">
                    <div class="me-3" style="font-size:1.4rem;color:#0f766e;"><i class="fas fa-shield-alt"></i></div>
                    <div class="flex-grow-1" style="padding: 8px;">
                        <div class="h6 mb-1">DNS Pro
                            {if $dnsProState == 'active'}
                                <span class="badge badge-success" style="margin-left:.25rem;">ATIVO</span>
                            {elseif $dnsProState == 'idle'}
                                <span class="badge badge-info" style="margin-left:.25rem;">Disponível para configurar</span>
                            {else}
                                <span class="badge badge-secondary" style="margin-left:.25rem;">Em breve</span>
                            {/if}
                        </div>
                        <div class="small text-muted">Gerencie os registros DNS diretamente na Link Nacional, com recursos e controle.</div>
                        <ul class="small text-muted mb-3" style="list-style:disc;padding-left:1.25rem;padding-top:0.88rem;">
                            {foreach $nsMap.dnspro as $nsItem}
                                <li>{$nsItem|escape}</li>
                            {/foreach}
                        </ul>
                    </div>
                </div>
                <div class="mt-auto">
                    {if $dnsProState == 'active'}
                        <a class="btn btn-outline-secondary btn-sm" href="{$dnsManagerUrl|escape}">Gerenciar registros</a>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-start mb-2">
                    <div class="me-3" style="font-size:1.4rem;color:#fd7e14;"><i class="fas fa-server"></i></div>
                    <div class="flex-grow-1" style="padding: 8px;">
                        <div class="h6 mb-1">cPanel
                            {if $mode == 'cpanel'}
                                <span class="badge badge-success" style="margin-left:.25rem;">ATIVO</span>
                            {elseif $services.cpanel}
                                <span class="badge badge-info" style="margin-left:.25rem;">Disponível</span>
                            {elseif $cartCpanel}
                                <span class="badge badge-secondary" style="margin-left:.25rem;">Contratar</span>
                            {else}
                                <span class="badge badge-info" style="margin-left:.25rem;">Disponível</span>
                            {/if}
                        </div>
                        <div class="small text-muted">Esse painel de controle disponibiliza para o domínio diversos recursos como: e-mails, FTP, cron jobs, banco de dados, site e muito mais.</div>
                        <ul class="small text-muted mb-3" style="list-style:disc;padding-left:1.25rem;padding-top:0.88rem;">
                            {foreach $nsMap.cpanel as $nsItem}
                                <li>{$nsItem|escape}</li>
                            {/foreach}
                        </ul>
                        <div class="small text-muted">Os registros DNS são gerenciados no painel do cPanel.</div>
                    </div>
                </div>
                <div class="mt-auto">
                    {if $mode != 'cpanel' && $services.cpanel}
                        {if ($nsOk || $nsCached) && $domainStatus == 'Active'}
                            <form method="post" class="m-0" onsubmit="return confirm('Apontar o domínio para os nameservers do cPanel?');">
                                <input type="hidden" name="token" value="{$token}" />
                                <input type="hidden" name="dnspro_action" value="apply_preset" />
                                <input type="hidden" name="preset" value="cpanel" />
                                <button type="submit" class="btn btn-primary btn-sm">Ativar</button>
                            </form>
                        {/if}
                    {elseif $mode != 'cpanel' && $cartCpanel}
                        <a class="btn btn-primary btn-sm" href="{$cartCpanel|escape}">Contratar</a>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-start mb-2">
                    <div class="me-3" style="font-size:1.4rem;color:#0d6efd;"><i class="fas fa-cogs"></i></div>
                    <div class="flex-grow-1" style="padding: 8px;">
                        <div class="h6 mb-1">DirectAdmin
                            {if $mode == 'directadmin'}
                                <span class="badge badge-success" style="margin-left:.25rem;">ATIVO</span>
                            {elseif $services.directadmin}
                                <span class="badge badge-info" style="margin-left:.25rem;">Disponível</span>
                            {elseif $cartDirectadmin}
                                <span class="badge badge-secondary" style="margin-left:.25rem;">Contratar</span>
                            {else}
                                <span class="badge badge-info" style="margin-left:.25rem;">Disponível</span>
                            {/if}
                        </div>
                        <div class="small text-muted">Esse painel de controle disponibiliza para o domínio diversos recursos como: e-mails, FTP, cron jobs, banco de dados, site, terminal, Node.js e muito mais.</div>
                        <ul class="small text-muted mb-3" style="list-style:disc;padding-left:1.25rem;padding-top:0.88rem;">
                            {foreach $nsMap.directadmin as $nsItem}
                                <li>{$nsItem|escape}</li>
                            {/foreach}
                        </ul>
                        <div class="small text-muted">Os registros DNS são gerenciados no painel do DirectAdmin.</div>
                    </div>
                </div>
                <div class="mt-auto">
                    {if $mode != 'directadmin' && $services.directadmin}
                        {if ($nsOk || $nsCached) && $domainStatus == 'Active'}
                            <form method="post" class="m-0" onsubmit="return confirm('Apontar o domínio para os nameservers do DirectAdmin?');">
                                <input type="hidden" name="token" value="{$token}" />
                                <input type="hidden" name="dnspro_action" value="apply_preset" />
                                <input type="hidden" name="preset" value="directadmin" />
                                <button type="submit" class="btn btn-primary btn-sm">Ativar</button>
                            </form>
                        {/if}
                    {elseif $mode != 'directadmin' && $cartDirectadmin}
                        <a class="btn btn-primary btn-sm" href="{$cartDirectadmin|escape}">Contratar</a>
                    {/if}
                </div>
            </div>
        </div>
    </div>
</div>
{/if}
