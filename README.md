# Presell Builder

MVP em PHP 8.1+, HTML, CSS e JavaScript, com persistência JSON e sem banco de dados.

## Arquitetura

- `app/helpers.php`: autenticação, consentimento, tracking, HMAC e operações JSON com bloqueio.
- `app/config.php`: configuração local por `.env`.
- `admin/`: login, painel e logout por POST.
- `index.php`, `review.php`, `bridge.php`: páginas parametrizadas por campanha.
- `postback.php`: endpoint de eventos autenticados.
- `storage/`: dados privados de execução, ignorados pelo Git e bloqueados por HTTP.
- `router.php`: proteção equivalente dos arquivos internos no servidor local PHP.

## Instalação local (PowerShell)

1. Instale PHP 8.1+ e Python 3 para executar os testes HTTP.
2. Execute `Copy-Item .env.example .env` somente se ainda não houver configuração local.
3. Defina `APP_ADMIN_USER`, `APP_ADMIN_PASSWORD_HASH`, `POSTBACK_SECRET` e `APP_BASE_URL` em `.env`.
   Não existe senha administrativa padrão. Configuração sem hash desabilita o login;
   configuração sem segredo desabilita o postback.
4. Para gerar o hash sem colocar a senha no histórico, salve temporariamente fora do repositório um script PHP que leia a senha com `fgets(STDIN)` e aplique `password_hash(trim(...), PASSWORD_DEFAULT)`.
   Use uma senha nova e exclusiva. Gere o segredo com `bin2hex(random_bytes(32))` localmente.
5. Inicie com `php -S localhost:8000 router.php` (ou `C:\php\php.exe -S localhost:8000 router.php`).
6. Acesse `http://localhost:8000/admin/login.php` e crie a campanha.

Não inicie o servidor local sem `router.php`: o servidor PHP não interpreta `.htaccess`.

## Consentimento e tracking

Sem aceite explícito, não gravamos tracking nem adicionamos parâmetros de rastreamento aos links de navegação ou da oferta.
Aceitar recarrega a página; rejeitar remove os parâmetros da URL atual. É possível alterar a decisão nas preferências.
A escolha usa um cookie SameSite com validade de 180 dias e Secure em HTTPS. Não mantemos tracking em localStorage.
A bridge só grava tráfego e redireciona automaticamente com aceite. A oferta continua acessível sem aceite.

Os parâmetros permitidos incluem GCLID, GBRAID, WBRAID, UTMs, SubIDs e alguns parâmetros ValueTrack (`campaignid`, `adgroupid`, `creative`, `keyword`, `device`, entre outros).
SubIDs preservam maiúsculas e minúsculas. Arrays, controles e valores acima de 512 bytes são descartados.
O URL original da oferta permanece como configurado pelo administrador, incluindo o identificador de afiliado.
Logs do servidor/provedor podem conter a URL recebida: configure retenção e minimização na hospedagem.

## Postback

Envie POST `application/x-www-form-urlencoded` com `campaign`, `status`, `amount`, `order_id` e `signature`.
A assinatura é HMAC-SHA256 hexadecimal (minúsculo) do texto exato:

```text
campaign|status|amount|order_id
```

Use o `POSTBACK_SECRET` como chave no remetente confiável. Não envie esse segredo ao navegador.
Todos os campos assinados devem ser strings e não podem conter `|`. O valor monetário aceita ponto decimal e até duas casas.
Campanha deve existir. Pedido deve ser não vazio e conter apenas letras, números, `_`, `.`, `:`, `-`.

- `approved`, `paid`, `completed`: confirmação de venda.
- `refunded`, `chargeback`, `cancelled`: reversão autenticada; o pedido deixa de contar como venda.
- Cliques, pending e accepted não confirmam vendas.
- Eventos repetidos por campanha/pedido/status não são gravados novamente.
- A contagem exige `accepted=true`, `verified=true`, `source=postback` e pedido válido; deduplica pedidos e preserva reversões.
- Eventos antigos sem `verified=true` não entram na contagem; não são apagados nem certificados retroativamente.
- Ausência de segredo retorna 503; assinatura inválida retorna 401; dados inválidos retornam 422.

Este é um contrato genérico de postback. As redes SmartADV, BuyGoods, ClickBank, Hotmart, Eduzz e Digistore24 aparecem no cadastro, mas ainda precisam de adaptadores próprios de payload e autenticação. Não conecte webhooks reais diretamente sem esse trabalho.

## Segurança administrativa

Login, criação de campanhas e logout validam CSRF. O login renova o ID da sessão.
Cookies de sessão usam HttpOnly, SameSite=Lax e Secure em HTTPS. Cinco tentativas inválidas bloqueiam novas tentativas por até 15 minutos, com persistência no servidor.
O limite é global para este MVP de administrador único; considere limitação adicional no servidor ao escalar.
URLs de oferta aceitam somente HTTP/HTTPS sem credenciais. Slugs e redes são validados no servidor.
Leituras e alterações JSON usam locks; falhas de armazenamento não são tratadas como sucesso.
O armazenamento continua em arquivos locais: mantenha backups e migre para um banco transacional ao escalar.

## Hospedagem

Para Apache/LiteSpeed compatível, copie também os arquivos `.htaccess`, configure PHP 8.1+, HTTPS e uma `.env` exclusiva no servidor.
A aplicação deve poder escrever em `storage/`; não conceda permissão 777.
Antes de usar em produção, confirme que `/.env`, `/storage/campaigns.json`, `/app/helpers.php` e `/tests/` retornam 403.
O `.htaccess` precisa ser respeitado pelo servidor. Em outra configuração, crie regras equivalentes ou mova dados e configuração para fora da raiz pública.
A publicação e a validação no servidor Hostinger ainda não foram executadas.

## Testes

```powershell
C:\php\php.exe tests/smoke_test.php
python tests/http_test.py
node tests/consent_test.js
```

Os testes HTTP usam uma cópia temporária isolada com credenciais fictícias e não modificam campanhas, credenciais ou eventos reais.

## Git

`.env`, variantes locais, JSONs de execução e logs são ignorados. `.env.example` contém apenas valores vazios ou exemplos públicos.
Antes de adicionar arquivos, revise `git status --short`, `git check-ignore .env storage/campaigns.json` e o conteúdo do staging.

```bash
git init
git switch -c feature/security-mvp
git add .
git diff --cached
git commit -m "fix: secure consent, admin and postback flows"
```

Use `main` para versões estáveis e `feature/*` ou `hotfix/*` para mudanças revisadas.
Nunca versionar senhas, hashes reais, tokens ou dados de visitantes. Consulte o [CHANGELOG.md](CHANGELOG.md) para as mudanças e validações de cada versão.

## Pendências

Adaptadores das redes, template de modal, editor visual, integração com Google Sheets/Apps Script e publicação validada na Hostinger permanecem pendentes.
