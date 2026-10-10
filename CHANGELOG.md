# Histórico de mudanças

## 0.2.0 — 2026-10-10

- Gerador com um único campo obrigatório: o link de afiliado. Slug e textos são gerados automaticamente, usando informações públicas da oferta quando disponíveis.
- Importação HTTP/HTTPS com redirecionamentos, metadados Open Graph, identificação de rede por domínio e idioma en/pt. Links originais de afiliado são preservados; ofertas inacessíveis produzem um modelo básico claramente identificado.
- Template de modal com fundo desfocado, identificação de página afiliada e escolhas reais de rastreamento. Imagens disponíveis são importadas e armazenadas fora do Git.
- Prévia integrada, ajuste opcional de idioma e download de HTML com CSS, JavaScript e imagem embutidos. O HTML estático não inclui persistência de tráfego nem servidor de postback.
- Login direciona ao gerador. Cadastro manual continua disponível como opção avançada.
- Consultas bloqueiam destinos privados, IPs reservados, protocolos/portas especiais e redirecionamentos perigosos; DNS fixado por conexão, TLS validado e limites de tamanho/tempo.
- Validação: 34 verificações de importação, 68 HTTP, 18 PHP de tracking/postback e 17 JavaScript de consentimento passaram. Importação HTTPS real validada em example.com. Não houve teste contra links reais de afiliado nem validação visual automatizada em navegador.

## 0.1.1 — 2026-10-10

- Configuração inicial do administrador no navegador, disponível somente em loopback com Host local e enquanto não houver hash de senha.
- Login local encaminha para a configuração inicial; o formulário valida CSRF, confirmação de senha e salva somente o hash em um arquivo de ambiente ignorado pelo Git.
- Configuração exige bloqueio do arquivo e nova verificação antes da escrita para impedir sobrescrever um administrador já configurado.
- Validação: 55 verificações HTTP passaram, incluindo configuração inicial, senha fraca, confirmação divergente, Host não local, CSRF e login com a senha cadastrada; sintaxe dos arquivos PHP alterados validada.

## 0.1.0 — 2026-10-10

Primeira versão do MVP Presell Builder, em PHP 8.1+, HTML, CSS e JavaScript, sem banco de dados e com persistência em JSON.

### Implementado

- Painel para criar e atualizar campanhas, com personalização de textos, preço, CTA e URL da oferta.
- Páginas de presell, review e bridge.
- Consentimento explícito com aceite, rejeição e alteração da escolha. Tracking só é gravado e encaminhado após aceite; SubIDs preservam maiúsculas e minúsculas.
- Postback genérico autenticado por HMAC-SHA256, validação de campanha/pedido/valor, deduplicação e registro de reversões. Cliques não são vendas; eventos antigos sem comprovação de autenticação não entram na contagem.
- Login sem senha padrão, CSRF em login/campanhas/logout, renovação de sessão e limite de tentativas. Mudanças no hash administrativo invalidam sessões antigas.
- Bloqueio HTTP dos arquivos internos via `.htaccess` e router local; headers contra cache e vazamento de parâmetros por Referer.
- Bloqueio nas operações JSON para evitar perda de eventos em gravações simultâneas.
- `.gitignore`, configuração de exemplo sem segredos e instruções de instalação, segurança e versionamento no README.

### Validação

- 18 verificações PHP de assinatura, consentimento, tracking e contagem de vendas.
- 45 verificações HTTP em ambiente temporário isolado, incluindo autenticação, CSRF, proteção de arquivos, postbacks e gravações concorrentes.
- 11 verificações JavaScript das escolhas de consentimento, sem navegador real.
- Sintaxe dos 13 arquivos PHP verificada e JavaScript validado com Node.js.

Os testes não usam credenciais nem dados privados de execução. As regras `.htaccess` ainda precisam ser validadas no servidor de hospedagem; a proteção HTTP foi testada com o router PHP local.

### Configuração necessária

A senha administrativa padrão antiga foi desativada na configuração local. Defina um novo hash de senha antes de acessar o painel. Configure um segredo exclusivo para o remetente confiável dos postbacks.

### Pendências

- Adaptadores próprios de autenticação e payload para SmartADV, BuyGoods, ClickBank, Hotmart, Eduzz e Digistore24; atualmente essas redes aparecem apenas no cadastro.
- Template de modal e editor visual de páginas.
- Integração com Google Sheets e Apps Script.
- Publicação e validação na Hostinger.
