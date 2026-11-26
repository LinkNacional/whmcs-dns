{if $error}
    <div class="alert alert-warning mb-3">{$error|escape}</div>
{else}
<h2 class="mb-1">DNS do seu domínio</h2>
<p class="text-muted mb-4">Escolha como deseja gerenciar os servidores de DNS do domínio <strong>{$domain|escape}</strong>.</p>

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

<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-start mb-2">
                    <div class="me-3" style="font-size:1.4rem;color:#3b5bfd;"><i class="fas fa-globe"></i></div>
                    <div class="flex-grow-1">
                        <div class="h6 mb-1">DNS Padrão <span class="badge badge-info" style="margin-left:.25rem;">Recomendado</span></div>
                        <div class="small text-muted">Utilize nossos servidores DNS para gerenciar seu domínio de forma automática.</div>
                    </div>
                </div>
                <div class="small text-muted mb-3">{$defaultNsText|escape}</div>
                <div class="mt-auto">
                    {if $mode == 'default'}
                        <div class="badge badge-success">Em uso neste domínio</div>
                    {elseif $nsOk || $nsCached}
                        <div class="badge badge-light" style="border:1px solid #198754;color:#198754;">Disponível este domínio</div>
                    {else}
                        <div class="badge badge-light" style="border:1px solid #b02a37;color:#b02a37;">Indisponível para este domínio</div>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-start mb-2">
                    <div class="me-3" style="font-size:1.4rem;color:#6f42c1;"><i class="fas fa-cloud"></i></div>
                    <div class="flex-grow-1">
                        <div class="h6 mb-1">DNS Personalizado</div>
                        <div class="small text-muted">Use os nameservers da sua hospedagem ou do seu provedor de DNS.</div>
                    </div>
                </div>
                <div class="small text-muted mb-3">
                    Atuais: {if $nsText}{$nsText|escape}{else}—{/if}
                </div>
                <div class="mt-auto">
                    {if $mode == 'custom'}
                        <span class="badge badge-success" style="margin-right:.5rem;">ATIVO</span>
                    {/if}
                    <a class="btn btn-primary" href="{$nativeNsUrl|escape}">Configurar</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-start mb-2">
                    <div class="me-3" style="font-size:1.4rem;color:#0f766e;"><i class="fas fa-shield-alt"></i></div>
                    <div class="flex-grow-1">
                        <div class="h6 mb-1">DNS Pro</div>
                        <div class="small text-muted">Gerencie os registros DNS diretamente na Link Nacional, com recursos e controle.</div>
                    </div>
                </div>
                <div class="small text-muted mb-3">{$nsMapText.dnspro|escape}</div>
                <div class="mt-auto">
                    {if $dnsProState == 'active'}
                        <span class="badge badge-success">ATIVO</span>
                        <a class="btn btn-outline-secondary btn-sm" href="{$dnsManagerUrl|escape}" style="margin-left:.5rem;">Gerenciar registros</a>
                    {elseif $dnsProState == 'idle'}
                        <span class="badge badge-info" style="margin-right:.5rem;">Disponível para configurar</span>
                    {else}
                        <span class="badge badge-secondary">Em breve</span>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    {if $services.cpanel || $mode == 'cpanel'}
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-start mb-2">
                    <div class="me-3" style="font-size:1.4rem;color:#fd7e14;"><i class="fas fa-server"></i></div>
                    <div class="flex-grow-1">
                        <div class="h6 mb-1">cPanel</div>
                        <div class="small text-muted">Aponte o domínio para o seu plano de hospedagem cPanel da Link Nacional.</div>
                    </div>
                </div>
                <div class="small text-muted mb-3">{$nsMapText.cpanel|escape}</div>
                <div class="mt-auto">
                    {if $mode == 'cpanel'}
                        <span class="badge badge-success">ATIVO</span>
                    {else}
                        <span class="badge badge-light" style="border:1px solid #198754;color:#198754;">Disponível</span>
                    {/if}
                    <div class="small text-muted mt-2">Os registros DNS são gerenciados no painel do cPanel.</div>
                </div>
            </div>
        </div>
    </div>
    {/if}

    {if $services.directadmin || $mode == 'directadmin'}
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-start mb-2">
                    <div class="me-3" style="font-size:1.4rem;color:#0d6efd;"><i class="fas fa-cogs"></i></div>
                    <div class="flex-grow-1">
                        <div class="h6 mb-1">DirectAdmin</div>
                        <div class="small text-muted">Aponte o domínio para o painel DirectAdmin da Link Nacional.</div>
                    </div>
                </div>
                <div class="small text-muted mb-3">{$nsMapText.directadmin|escape}</div>
                <div class="mt-auto">
                    {if $mode == 'directadmin'}
                        <span class="badge badge-success">ATIVO</span>
                    {else}
                        <span class="badge badge-light" style="border:1px solid #198754;color:#198754;">Disponível</span>
                    {/if}
                    <div class="small text-muted mt-2">Os registros DNS são gerenciados no painel do DirectAdmin.</div>
                </div>
            </div>
        </div>
    </div>
    {/if}
</div>

<p class="small text-muted mt-4">Alterações de nameservers podem levar até 24 horas para propagar. Para editar registros do DNS Pro, use <a href="{$dnsManagerUrl|escape}">DNS Manager</a>.</p>
{/if}
