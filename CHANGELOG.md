Versão 3.1.0 (08/08/2026)
* Novo: link "Verificar atualizações" na linha do HubGo em Plugins (MeuMouse\Hubgo\Core\Update_Checker)
    - A consulta é assíncrona (POST hubgo/v1/updates/check) e o resultado aparece ao lado do link, sem recarregar a página
    - Força uma nova consulta ao MDS: limpa o cache de 12h de atualização, a lista de versões do rollback e revalida a licença antes de responder
    - Quando há versão nova, o link "Atualizar agora" leva direto para a atualização do WordPress
    - Sem JavaScript, o próprio link executa a verificação no servidor e devolve o resultado como aviso
    - Novo filtro: Hubgo/Core/Update_Checker/Payload
* Compatibilidade com o plugin Frenet (Frenet Shipping Gateway) — tudo resolvido pelo lado do HubGo, sem alterar o plugin de terceiros:
    - Correção: a calculadora do HubGo cotava com valor de nota R$ 0,00. O plugin da Frenet lê o valor do carrinho, que está vazio na página de produto, e serviços cotados por valor declarado simplesmente não apareciam. A cotação por produto passa a ser ativada apenas nos pacotes do HubGo.
    - Novo: o prazo de entrega e a transportadora (Correios, Jadlog, Loggi) passam a ser lidos da resposta da API da Frenet e gravados como meta padrão da forma de entrega (delivery_time e carrier), o que devolve a data prometida ("Receba até dia X") na calculadora. Em lojas sem token (modo SOAP), o prazo é lido do rótulo da forma de entrega.
    - Correção: a página de produto exibia duas calculadoras. O simulador do plugin da Frenet passa a ser removido quando a calculadora do HubGo está ativa, com nova opção "Ocultar simulador da Frenet" no cartão da integração.
    - A opção "Importar rastreio" passa a ter efeito real: em pedidos enviados pela Frenet, códigos de rastreio sem transportadora definida recebem a Frenet como provedor (para o link) e a transportadora cotada como nome exibido. Nada é gravado no banco — o preenchimento acontece só na exibição.
* Novo: a data de entrega prometida no checkout passa a ser gravada no pedido (MeuMouse\Hubgo\Core\Delivery_Promise)
    - Grava prazo em dias úteis, data prometida, transportadora e forma de entrega, a partir da meta que o WooCommerce copia da forma de entrega para o item do pedido
    - Funciona no checkout clássico e no checkout em blocos (Store API)
    - Novos filtros: Hubgo/Delivery/Promise_Days, Hubgo/Delivery/Carrier_Meta_Keys. Nova ação: Hubgo/Delivery/Promise_Saved
* Novo: verificação diária de entregas atrasadas (MeuMouse\Hubgo\Core\Delivery_Watcher)
    - Pedidos com status "Pedido enviado" cuja data prometida passou disparam a ação Hubgo/Delivery/Overdue, uma única vez por pedido
    - Processamento em lotes de 50 pedidos por execução, com tolerância de 1 dia
    - Novos filtros: Hubgo/Delivery/Overdue_Enabled, Hubgo/Delivery/Overdue_Grace_Days, Hubgo/Delivery/Overdue_Query
* Joinotify: novo gatilho "Entrega atrasada" e novos marcadores
    - {{ hubgo_delivery_date }}, {{ hubgo_delivery_days }} e {{ hubgo_shipping_method }}, disponíveis em todos os gatilhos do HubGo
    - {{ hubgo_carrier_name }} passa a usar a transportadora cotada no checkout quando o código de rastreio ainda não tem transportadora definida
* Novo método público MeuMouse\Hubgo\Core\Delivery_Estimate::get_days_from_meta()
* Calculadora: o campo "Estado" do buscador de CEP passa a usar o seletor moderno do HubGo, com busca por nome ou sigla, navegação por teclado e a mesma aparência dos demais campos
    - Os estados continuam vindo da lista do WooCommerce (WC()->countries->get_states()), agora exibindo o nome do estado com a sigla ao lado, em vez de apenas a sigla
* Novo: transições suaves em toda a interface, no painel e na loja
    - Modais abrem e fecham com fade e um leve deslocamento, em vez de aparecerem de uma vez
    - A rolagem da página atrás do modal fica travada enquanto ele está aberto, sem deslocar o conteúdo do wp-admin
    - Correção: os avisos (toasts) não tinham animação de entrada e a pilha "pulava" quando um deles sumia. Agora entram pela direita, saem sem empurrar os demais e os que ficam deslizam para o lugar
    - Correção: a barra de progresso do aviso tinha a duração escrita em dois lugares, que podiam divergir
    - O esqueleto de carregamento das três telas dá lugar ao conteúdo em transição, em vez de trocar de uma vez
    - Troca de aba nas configurações, filtro de categoria nas integrações e ativação da licença passam a trocar o painel em transição
    - Na calculadora da loja: troca entre o formulário de CEP e a cotação, entrada em cascata das formas de entrega, seleção da opção e busca de endereço
    - Tudo respeita a preferência "reduzir movimento" do sistema operacional: as animações viram um fade curto, e os indicadores de carregamento continuam girando

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
* Novo: menu próprio do HubGo no painel, com as subpáginas Configurações, Integrações e Licença
    - A página de Configurações passa a ter as abas Geral, Aparência, Textos e Sobre
    - A aba Sobre reúne preferências de manutenção, status do sistema e a restauração das configurações padrão
    - O slug hubgo-settings continua válido: links antigos seguem funcionando
* Novo: página de Integrações com grade de aplicações, filtro por categoria e modal de configuração por integração
    - Joinotify (Pro), Melhor Envio (Pro, em breve) e Frenet
    - Instalação e ativação do plugin da integração direto do painel (Frenet)
    - Selo "Pro" nas integrações que dependem de licença ativa
* Novo: tela de Licença própria, com formulário de ativação, sincronização e desativação do site
* Novos endpoints REST: GET hubgo/v1/integrations, POST hubgo/v1/plugins/install, POST hubgo/v1/settings/reset, GET hubgo/v1/license, POST hubgo/v1/license/activate, POST hubgo/v1/license/deactivate, POST hubgo/v1/license/sync
* Novos filtros: Hubgo/Integrations/Cards, Hubgo/Integrations/Card, Hubgo/Integrations/Categories, Hubgo/Admin/Integrations/Cards, Hubgo/Admin/Integrations/Bootstrap_Data, Hubgo/Admin/System_Status, Hubgo/Core/Assets/Admin_Pages, Hubgo/Core/License/Payload, Hubgo/Core/Plugin_Installer/Allowed_Hosts
* Novo: campo de medida (tipo dimension) nas configurações de aparência, com seletor de unidade (rem, em, px e %)
    - Arredondamentos, espaçamentos, altura, tamanho da fonte e desfoque deixam de ser sliders e passam a aceitar a unidade escolhida
    - O valor é gravado com a unidade ("1.5rem"); valores gravados antes continuam válidos e são lidos em px
    - O widget do Elementor passa a oferecer as mesmas unidades nos controles equivalentes
* Padronização visual dos campos do painel: mesma altura (3rem), mesmo raio e mesma borda em campos, seletores, senha e cor
    - O seletor de cor passa a ter amostra quadrada de 3rem ao lado do campo de código hexadecimal, com botão de redefinir para a cor padrão
    - Foco passa a ser borda de 2px na cor primária, sem sombra
    - Correção: botões sem estilo próprio herdavam a aparência nativa do sistema, porque o preflight do Tailwind está desativado
* Correção de bugs:
    - Textos com aspas apareciam escapados no painel (&quot;), porque as strings entregues ao SPA passavam por esc_html__() e o Vue escapa novamente ao renderizar
    - A opção "Atualizações automáticas" era lida por MeuMouse\Hubgo\Core\License mas não existia nas configurações nem nos valores padrão, então nunca ligava
    - A opção "Exibição dos métodos de entrega" tinha valor padrão mas não aparecia na tela de configurações
    - app/dist estava no .gitignore, o que deixaria os pacotes das novas telas fora do zip de distribuição

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