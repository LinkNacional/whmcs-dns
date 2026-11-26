# Changelog

## 1.3.0 — em teste (homolog)

### DNS Pro (produto)
- Modulo de servidor `dnspro` (Create/Suspend/Unsuspend/Terminate) integrado ao addon.
- `whmcs_dns/lib/service.php`: provisiona/apaga zona via Cardo e aplica os nameservers do DNS Pro quando o registrar permite.
- Card DNS Pro no dominio: ATIVO / Disponivel + Ativar / Contratar (link de carrinho com ciclo derivado do produto) / Em breve.
- Novo setting do addon: `cart_pid_dnspro`.

## 1.2.0 — 2026-11-26

### Segurança e limites
- A1: limite de registros por zona (`max_records_per_zone`, default 100), throttle de 30 req/min por cliente (`mod_whmcs_dns_throttle`) e default de 5 zonas por cliente.
- A2: reserva da zona em transação (`SELECT ... FOR UPDATE`), sem corrida no limite de zonas.
- A3: autorização de DNS por status do domínio (`Active`, `Grace`, `Redemption`), bloqueio de conflito de titularidade com log, e `disable_dns` liberado para zona já existente.
- M1/M3: `zones.config` não guarda mais credenciais; migração idempotente sanitiza linhas antigas; `config` removido dos vars do template.
- M2: mensagens genéricas ao cliente com detalhe em `logModuleCall`/`logActivity` (`WhmcsDnsUserException` para erros de negócio).
- M4: token CSRF em todos os formulários POST do client area.
- M5: auditoria (`WHMCS-DNS:`) de todas as mutações + listagem no admin.

### Operação
- M7: `deactivate()` preserva `zones`/`records`; purga explícita no admin com confirmação e log; versão 1.2.0.
- B2: SPF deixa de aparecer no select quando o provider não suporta (Bunny) e é convertido em TXT no servidor.

### Nova tela DNS (Nomes Servidores)
- Item "DNS (Nomes Servidores)" no menu do domínio, substituindo o item nativo; página com cards (DNS Padrão, DNS Personalizado, DNS Pro, cPanel, DirectAdmin), breadcrumb e sidebar.
- Ação "DNS Padrão" via `DomainUpdateNameservers` com token, throttle e log; aba nativa sem a opção padrão e com botão Cancelar.
- Estado por domínio (`mod_whmcs_dns_domain_state`) com `last_ns` e `default_ns`.

### Dependências
- `namingo/plexdns` passa a resolver do fork `LinkNacional/cardo-dns` (v1.1.4), com backoff 429/503 e custom nameservers/SOA por zona no provider Bunny.
