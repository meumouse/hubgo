Versão 3.0.1 (07/08/2026)
* Correção de bugs:
    - Calculadora de frete não respeitava a prioridade das zonas de entrega de acordo com o CEP informado. O estado do destino era lido da sessão do cliente (ou do endereço base da loja) em vez de ser derivado do CEP, fazendo uma zona por estado com ordem menor vencer zonas que o CEP realmente atendia.
    - Calculadora de frete alterava o carrinho do cliente (adicionava e removia o produto) para avaliar o frete grátis.
    - Calculadora de frete sobrescrevia o CEP de cobrança e entrega salvo na sessão do cliente.
    - Frete grátis era injetado manualmente na tabela, ignorando a zona, o filtro woocommerce_package_rates e a opção de ocultar demais fretes.
    - Cálculo gravava no mesmo cache de sessão usado pelo checkout.
    - CEP inválido retornava a mensagem de "nenhuma forma de entrega disponível" em vez de um erro de validação.
    - Plugin alterava silenciosamente a opção woocommerce_default_customer_address do WooCommerce.
* Novo: resolução de UF a partir do CEP (faixas oficiais dos Correios) via MeuMouse\Hubgo\Core\Postcode_Locator
* Novo: parâmetro opcional "country" no endpoint POST hubgo/v1/shipping/calculate
* Novo: com o modo de depuração de entrega do WooCommerce ativo, o endpoint retorna a zona correspondente
* Novos filtros: Hubgo/Shipping_Calculator/Postcode_State_Map, Hubgo/Shipping_Calculator/Resolved_State, Hubgo/Shipping_Calculator/Country, Hubgo/Shipping_Calculator/Destination, Hubgo/Shipping_Calculator/Zone
* O filtro Hubgo/Shipping_Calculator/Rates passa a receber a zona correspondente como terceiro argumento
* Novo: licenciamento e atualizações pelo MDS PHP SDK (meumouse/mds-php-sdk ^1.1), instalado via Composer
    - Tela "HubGo - Licença" para ativar, desativar e revalidar a chave, com aviso quando a licença está ausente, inválida ou expirada
    - Verificação de atualização assinada (ed25519) e restrita a licenças válidas, com cache de 12h e heartbeat diário via WP-Cron
    - Suporte a licenças de bundle: uma única chave cobre vários produtos (ex.: "Clube M"), com os produtos inclusos disponíveis em MeuMouse\Hubgo\Core\License::get_bundle()
    - Dados do plano, URL de renovação, expiração do suporte e limite de ativações acessíveis por License::get_data() sem nova requisição
* Removido: verificador de atualizações próprio (MeuMouse\Hubgo\API\Updater) que consultava um JSON estático em packages.meumouse.com

Versão 3.0.0 (06/07/2026)
* Mudança de arquitetura: interface de configurações reescrita em Vue 3 + Vite (padrão Joinotify)
* API First: todas as ações AJAX legadas substituídas pela REST API (namespace hubgo/v1)
* Compatibilidade com o Joinotify v2 (nova API funcional: triggers, placeholders e disparo de workflows)
* Base modular e extensível (registro de integrações e schema de configurações filtráveis)
* Correção de segurança: endpoints REST passam a exigir capability + nonce

Versão 2.2.0 (12/03/2026)
* Correção de bugs:
    -Pedidos com status 'Pedido enviado' não são exibidos na página de pedidos.
* Recurso adicionado: Status de 'Pedido enviado' nas ações em massa da página de pedidos

Versão 2.1.0 (06/03/2026)
* Recurso adicionado: Novo status de pedido -> Pedido enviado
* Recurso adicionado: Meta box para cadastro de código de rastreio no pedido
* Recurso adicionado: Integração com Joinotify

Versão 2.0.0 (25/02/2026)
* Correção de compatibilidade com Melhor Envio
* Otimizações
* Mudança de arquitetura para MACI (Modular Autoload Class Initialization)

Versão 1.4.0 (11/08/2025)
* Correção de bug
    - Carregamento de opções de frete

Versão 1.3.0 (16/02/2024)
* Correção de bugs
* Otimizações
* Novo recurso adicionado: Ativar cálculo automático de frete

Versão 1.2.6 (18/12/2023)
* Correção de bugs

Versão 1.2.5 (31/10/2023)
* Compatibilidade com High Performance Order Storage (HPOS) do WooCommerce

Versão 1.2.0 (09/10/2023)
* Correção de bugs
* Otimizações

Versão 1.1.7 (26/07/2023)
* Correção de bugs
* Otimizações

Versão 1.1.5 (19/07/2023)
* Correção de bugs

Versão 1.1.0 (14/07/2023)
* Correção de bugs

Versão 1.0.0 (13/07/2023)
* Versão inicial