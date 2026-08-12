# Fórmula / Lab (manipulação)

# Apresentação

Este manual descreve, tela a tela e campo a campo, como operar o TAO Lab — o conjunto de módulos que substitui o sistema de origem na farmácia de manipulação: cadastros, orçamento, entrada de notas fiscais, controle de estoque por lote, produção com rastreabilidade, rótulo e livro de receituário.

As telas funcionam no computador e no celular, pelo portal (solucoesetao.com.br/robos → menu Fórmulas). As imagens deste manual foram capturadas do próprio portal.

### Convenções

• Campos marcados com * (asterisco) são obrigatórios.

• Onde houver "Regra:", trata-se de um comportamento automático ou validação do sistema.

*• O cadastro de cliente/paciente é ÚNICO em toda a solução — o mesmo contato do Agente de WhatsApp, do CRM e das Campanhas.*

• Ícones usados nas telas: 🖨 imprimir · ✔ aprovar/confirmar · ✕ recusar/cancelar · ↻ repetir/reprocessar · ⚖ inventário · ↔ kardex (extrato) · 🛒 gerar cotação · 🔒 controlado.

# Primeiros passos — acesso e navegação

O TAO Lab é usado pelo navegador (Chrome, Edge ou Firefox), no computador ou no celular — não há nada para instalar.

### Como entrar no sistema
1. Abra o endereço solucoesetao.com.br/robos no navegador.
1. Digite seu e-mail e senha (fornecidos pelo administrador) e clique em Entrar.
1. Você chega à tela inicial do portal. Os módulos da farmácia ficam no menu "Fórmulas"; os cadastros compartilhados, no menu "Cadastros".

**Regra: **O acesso é liberado por perfil. Se faltar uma tela ou você esquecer a senha, fale com o administrador.

### Como navegar

O menu organiza o sistema em grupos. Os principais para o dia a dia:

• Cadastros — cliente/paciente, prescritores, fornecedores, formas farmacêuticas e produtos/ativos.

• Fórmulas — Novo Orçamento, Histórico, Produção, Estoque (Entrada de NF, Lotes, Reposição, Inventário), Livro de Receituário, Controlados/SNGPC, Contas a Pagar e Configurações.

• Entregas e Caixa — última milha e financeiro (capítulos 21 e 22).

**Regra: **Em qualquer lista há uma busca no topo. Nos campos que buscam pessoas ou produtos, navegue pelo teclado: setas ↑↓ para percorrer e Enter para escolher.

### Quem faz o quê (perfis)

Cada pessoa vê apenas o que o seu perfil permite (mesmos perfis do TAO Neo): Administrador e Gestor têm acesso completo, inclusive Configurações e Controlados; o perfil Operacional vê o dia a dia (orçamento, produção, estoque). Algumas ações são restritas ao responsável técnico / Gestor: aprovar ou reprovar lote, aprovar orçamento e escriturar controlados.

# Como o sistema funciona — o fluxo do dia a dia

Antes de entrar tela a tela, veja o caminho que uma fórmula percorre, do pedido do cliente até a entrega. Cada etapa tem um capítulo detalhado adiante — aqui você entende como elas se encaixam.
1. CHEGA O PEDIDO. O cliente manda a receita pelo WhatsApp (ou vem pelo balcão). No CRM abre-se um card do atendimento, já vinculado ao contato.
1. MONTA-SE O ORÇAMENTO (cap. 6). A partir da receita, escolhe-se a forma farmacêutica e os ativos, com dose e quantidade; o sistema calcula a quantidade a pesar e o preço. O orçamento é enviado ao cliente pelo próprio card.
1. CLIENTE APROVA. O card é fechado como "ganho": a venda nasce no Caixa (cap. 22) e a entrega é registrada (cap. 21).
1. PRODUÇÃO (cap. 11). Gera-se a Ordem de Manipulação (OM). A FICHA DE PESAGEM orienta o manipulador — qual produto e quanto pesar de cada componente, com o lote (rastreabilidade). Imprimem-se a ficha e o RÓTULO (RDC 67).
1. MANIPULA E CONCLUI. O manipulador pesa, registra o lote usado e conclui a OM: o estoque é baixado dos lotes e a validade da fórmula é fixada. Havendo componente controlado, os dados da receita são escriturados no SNGPC (cap. 18).
1. ENTREGA E PÓS-VENDA. A fórmula é entregue (cap. 21) e o pagamento é baixado no Caixa. A manipulação fica registrada no Livro de Receituário (cap. 12).

Por trás desse fluxo, o ESTOQUE é alimentado pelas notas fiscais de compra (cap. 8), com controle de qualidade por lote (cap. 9) e reposição automática (cap. 10). E tudo o que o cálculo usa — teor, diluição, validade por forma, dose máxima — é CADASTRADO, não fica fixo no sistema: nos produtos (cap. 2) e nas formas farmacêuticas (cap. 20).

**Regra: **Em uma linha: Receita → Orçamento → Aprovação → Produção (OM + Ficha + Rótulo) → Conclusão (baixa de estoque + validade + SNGPC) → Entrega + Caixa → Livro. Os cadastros e o estoque sustentam tudo por trás.

# 1. Configurações da Farmácia

Menu Fórmulas → Configurações. Ponto de partida: sem estes dados o rótulo não pode ser emitido corretamente.

![](img/formula-lab/01.png)

*Figura — Tela de Configurações no portal*

### Dados da Farmácia

| Campo | Obrig. | Descrição e regra |
| --- | --- | --- |
| Razão social | Sim | Nome jurídico da farmácia. Sai no rótulo e na NF. |
| Nome fantasia | — | Nome comercial (aparece no topo do rótulo). |
| CNPJ | Sim | Somente números. Identifica a farmácia no rótulo e no fiscal. |
| Inscrição Estadual / Municipal | — | Para emissão fiscal. |
| Endereço, Bairro, Cidade, UF, CEP | Sim | Endereço completo — obrigatório no rótulo (RDC 67). |
| Telefone / E-mail | — | Contato da farmácia. |
| Farmacêutico(a) RT | Sim | Nome do Responsável Técnico. Obrigatório no rótulo. |
| CRF / UF do CRF | Sim | Registro do RT no Conselho. Sai no rótulo. |
| AFE, CEVS, CRF-PJ, Autorização Especial | — | Números das licenças sanitárias, para conformidade. |

**Regra: **Clique em "Salvar dados da farmácia". Esses dados alimentam automaticamente todo rótulo emitido.

### Motor farmacotécnico v2

Marque "Ativar no editor de orçamentos" para ligar: equivalência do sinônimo (sal↔base), alerta de dose máxima, trava de substância restrita/bloqueada e uso do teor real do lote no cálculo. Desligado, o cálculo permanece no modo simples. Recomenda-se manter ligado.

# 2. Produtos / Ativos

Menu Fórmulas → Ativos. Cadastro de matérias-primas (MP) e embalagens.

![](img/formula-lab/02.png)

*Figura — Lista de ativos*

Use a busca por nome ou código. Clique no nome de um produto para ver os detalhes e os sinônimos. Para cadastrar, clique em "+ Novo Ativo".

### Buscar e navegar na lista
1. Digite parte do nome ou o código na busca do topo — a lista filtra na hora.
1. Use o filtro Matéria-Prima / Embalagem para separar os dois tipos; o contador no topo mostra quantos itens existem em cada grupo.
1. Ajuste quantos itens ver por página (20/30/50) e percorra as páginas pelo rodapé.
1. Clique no nome de um item para ver os detalhes; use o lápis (editar) para alterar; "+ Novo Ativo" para cadastrar.

## Formulário de cadastro do ativo

![](img/formula-lab/03.png)

*Figura — Formulário Novo/Editar Ativo*

| Campo | Obrig. | Descrição e regra |
| --- | --- | --- |
| Nome | Sim | Nome do produto (gravado em maiúsculas). |
| Grupo | Sim | Matéria-Prima ou Embalagem. |
| Código | — | Código interno (FC). Regra: não pode repetir — se já existir em outro produto, o sistema recusa. |
| Unidade (compra) | — | Como o produto é comprado (G, ML, UN). |
| Unidade padrão (venda) | — | Unidade usada no orçamento (g, mg, ml, un). |
| Categoria | — | Classificação livre (ex.: ativo, excipiente). |
| Preço compra | — | Valor pago ao fornecedor. Atualizado automaticamente pela entrada de NF. |
| Custo/unid | — | Custo unitário para cálculo de margem. |
| Preço venda | — | Preço por unidade usado no orçamento. |
| Markup | — | Multiplicador de venda (venda = custo × markup). |
| DCB | — | Denominação Comum Brasileira. Usada para dose máxima e identificação. |
| Diluição (1:N) | — | Fator de diluição da MP (ex.: 20 = 1:20). Entra no cálculo da pesagem. |
| Teor (%) | — | Concentração real do princípio ativo. O motor divide a dose pelo teor. |
| Densidade (g/mL) | — | Para líquidos e cálculo de volume em cápsula (VOLAPA). |
| Fator de perda | — | Acréscimo por perda de processo (ex.: 1,05 = 5%). |
| Fator de correção | — | Correção farmacotécnica adicional (padrão 1). |
| Concentração (UI/UFC/g) | — | Para vitaminas/probióticos em UI, UFC ou BLH. |
| Restrição | — | Regra: "bloqueada" impede o uso no orçamento; "restrita" apenas alerta. Usado para controle (ex.: GLP-1). |
| Dose máxima / Unid. dose máx | — | Regra: com o Motor v2 ligado, dose acima deste valor destaca o campo em vermelho no orçamento. |
| Observações | — | Notas livres. |

**Regra: **Os campos técnicos (diluição, teor, densidade, fator de perda) alimentam o cálculo da fórmula no orçamento.

### Aba Sinônimos

Na tela de Ativos, a aba "Sinônimos" guarda os nomes alternativos que o sistema reconhece na prescrição (ex.: a receita diz "Vitamina C" e o produto cadastrado é "ÁCIDO ASCÓRBICO"). Com isso, o orçamento associa o ativo certo mesmo quando o médico usa outro nome.
1. Para associar: busque o ativo e vincule o termo alternativo. Quando você associa um ativo no editor de orçamento, o termo digitado já é salvo como sinônimo automaticamente.
1. Botão "Somente sem associação": lista os termos que apareceram em orçamentos mas ainda não têm ativo — associe-os para o sistema reconhecê-los da próxima vez.
1. Botão "↻ Reprocessar orçamentos": depois de criar um sinônimo, reaplica a associação nos orçamentos que tinham aquele termo em aberto, recalculando os valores.

### Importar catálogo por planilha (gestor)

A aba "📥 Importar planilha" (apenas gestor) carrega o catálogo inteiro de uma vez, a partir de uma planilha modelo (abas Materias-Primas, Embalagens, Tipos-Cápsula).
1. Preencha a planilha modelo e clique em "Pré-visualizar" — o sistema valida e mostra o que será criado/atualizado, SEM gravar nada.
1. Escolha o modo: "Incremental" (só cria/atualiza o que está na planilha) ou "Completo" (além disso, DESATIVA o que não estiver nela — use só para carga total de um catálogo novo).
1. Clique em "Importar agora" para efetivar. O sistema casa por código, preservando os cadastros e sinônimos já existentes.

**Regra: **Cuidado com o modo "Completo": ele desativa tudo que não estiver na planilha. Para atualizações parciais, use sempre "Incremental".

# 3. Prescritores

Menu Fórmulas → Prescritores. Médicos, dentistas, veterinários e nutricionistas.

![](img/formula-lab/04.png)

*Figura — Lista de prescritores*
1. Busque pelo nome ou número de registro; clique em "+ Novo Prescritor" para cadastrar ou no lápis para editar.
1. Ao informar o CEP no cadastro, o endereço (cidade/UF) é preenchido automaticamente.

## Formulário de cadastro do prescritor

![](img/formula-lab/05.png)

*Figura — Formulário Novo Prescritor*

| Campo | Obrig. | Descrição e regra |
| --- | --- | --- |
| Tratamento | — | Dr, Dra, etc. Aparece no rótulo antes do nome. |
| Nome | Sim | Nome do prescritor. |
| Tipo registro | — | CRM, CRO, CRMV, CRN… (conselho profissional). |
| Nº registro | — | Número no conselho. |
| UF registro | — | Estado do registro. |
| Especialidade | — | Especialidade médica/odontológica. |
| E-mail / Celular / Telefone | — | Contatos do prescritor. |
| Endereço / Cidade / UF / CEP | — | Endereço do consultório. |
| Observações | — | Notas livres. |

**Regra: **No orçamento, o campo Prescritor busca diretamente neste cadastro (por nome ou número). Não é obrigatório informar prescritor no orçamento.

# 4. Cliente / Paciente

O cliente é único em toda a solução (o mesmo do CRM/Agente/Campanha). É gerenciado na tela de Histórico (Fórmulas → Histórico): busque o cliente e use "Editar dados", ou "+ Novo Cliente".
1. Para localizar: digite o nome (mín. 3 letras) ou o WhatsApp na busca; use "Editar dados" para atualizar ou "+ Novo Cliente" para cadastrar.
1. Antes de criar um novo, confira se a pessoa já existe pelo WhatsApp — o cadastro é único e não deve ser duplicado.

![](img/formula-lab/06.png)

*Figura — Formulário Novo/Editar Cliente*

| Campo | Obrig. | Descrição e regra |
| --- | --- | --- |
| Nome | Sim | Nome do paciente. |
| Sexo | — | Feminino / Masculino. |
| Nascimento | — | Data de nascimento. |
| WhatsApp | Sim | Regra: é a CHAVE ÚNICA do cadastro. Se já existir um contato com esse número, o sistema reaproveita o mesmo (não duplica a pessoa). |
| E-mail | — | E-mail do cliente. |
| Características de saúde | — | Marcadores comuns: Obesidade, Colesterol, Pressão, Diabetes. Aparecem como selo ao abrir o cliente. |
| Alergias | — | Texto livre — destacado no atendimento. |
| Observações | — | Notas livres. |

**Regra: **Ao salvar, os dados valem para o CRM, o Agente e as Campanhas — é o mesmo contato. Não crie cadastros paralelos.

# 5. Fornecedores

Menu Cadastros → Fornecedores (também acessível por Fórmulas → Fornecedores — é o MESMO cadastro). É um cadastro ÚNICO de fornecedor, compartilhado por Fórmula e Cotações (mesma base) — essencial para casar as notas fiscais de compra e para a qualificação exigida pela RDC 67. A lista já traz os fornecedores criados automaticamente pelas notas; aqui você completa os dados.

![](img/formula-lab/07.png)

*Figura — Lista de fornecedores*
1. Busque pelo nome ou CNPJ; "+ Novo Fornecedor" para cadastrar, lápis para editar.
1. Preencha o CNPJ antes de importar a primeira nota daquele fornecedor — é o que casa o XML da NF ao cadastro. (Na Entrada de NF, se o CNPJ do emitente ainda não estiver cadastrado, há o botão "Cadastrar fornecedor com os dados da NF", que preenche tudo pelo XML.)

### Campos do cadastro

![](img/formula-lab/08.png)

*Figura — Formulário Novo/Editar Fornecedor*

| Campo | Obrig. | Descrição e regra |
| --- | --- | --- |
| Tipo pessoa / Nome | Sim | PJ ou PF e o nome/apelido do fornecedor (obrigatório). |
| Razão social / Fantasia | — | Nome oficial e fantasia (aparecem na NF). |
| CNPJ / CPF | — | Documento — é a CHAVE que casa a NF de compra ao fornecedor. Preencha antes de importar a primeira nota. |
| WhatsApp / Telefone / E-mail | — | Contatos para pedidos e cotações (WhatsApp só dígitos, com DDD). |
| Pessoa de contato | — | Nome do vendedor/atendente. |
| Tipo | — | Fabricante, distribuidor, importador ou transportadora. |
| Regime (CRT) | — | Simples Nacional, Simples excesso ou Regime Normal. |
| Inscrições / SUFRAMA / MAPA | — | Inscrição estadual, municipal e registros especiais. |
| Endereço / Cidade / UF / CEP | — | Endereço completo do fornecedor. |
| Prazo de pagamento | — | Ex.: 28/35/42 dias, boleto — usado para gerar as duplicatas em Contas a Pagar. |
| AFE + validade | — | Autorização de Funcionamento (ANVISA) e sua validade (RDC 67). |
| Autorização Especial + validade | — | AE para fornecer controlados (Portaria 344/98). |
| Licença/Alvará VISA + validade | — | Licença sanitária estadual/municipal. |
| Qualificado / data / avaliador | — | Marcação da qualificação do fornecedor (RDC 67). |
| Observações | — | Pedido mínimo, prazo de entrega, etc. |

**Regra: **Na lista, licenças (AFE/VISA) vencidas aparecem com selo vermelho, e a Entrada de NF exibe um aviso de conformidade no recebimento. Sem o CNPJ cadastrado, a NF não vincula ao fornecedor.

# O card do cliente — operando pelo CRM

O card é a central do atendimento: cada cliente/negócio tem um card no Kanban, e é nele que acontece TODA a operação — a conversa por WhatsApp, o registro do que foi combinado, o orçamento, o fechamento da venda e o acompanhamento pós-venda. Quem atende passa a maior parte do tempo aqui, sem precisar abrir outras telas.

![](img/formula-lab/09.png)

*Figura — A ficha do card — conversa, dados do cliente e o negócio*

### Onde fica e como abrir

O Kanban (menu CRM) mostra os cards em colunas — uma para cada fase do funil (ex.: Aguardando Atendimento → Em Conversa → Orçamento Enviado …; e, no Pós-vendas: Aguardando Produção → Em Produção → Pronto para Entrega → Entregue → NPS).
1. Clique em um card para abrir a ficha completa.
1. Use a busca no topo do Kanban para achar um card por nome, WhatsApp ou número da requisição.
1. Arraste o card entre as colunas para mudar a fase — ou use os botões de avançar/fechar dentro da própria ficha.

### A conversa (WhatsApp)

A ficha traz o chat com o cliente, como no WhatsApp: as mensagens entram e saem por ali, e tudo fica registrado.
1. Digite no campo de mensagem e clique em "Enviar" para responder o cliente.
1. Anexe imagens/arquivos pelo clipe.
1. Nota interna: alterne para o modo nota para escrever um recado que SÓ a equipe vê (não vai ao cliente).
1. Agendar mensagem (⏰): programe uma mensagem para uma data/hora futura (ex.: lembrete de retorno).

### Organização do atendimento

A ficha reúne os recursos para conduzir e não perder o atendimento:

| Campo | Obrig. | Descrição e regra |
| --- | --- | --- |
| Responsável | — | Quem faz qualquer alteração no card vira o responsável por ele. Só a equipe do negócio aparece na lista de responsáveis. |
| Tags (etiquetas) | — | Marcadores coloridos para classificar o card (campanha, prioridade, origem…). |
| Lembretes 🔔 | — | Agende um follow-up com data/hora — o sistema notifica quando vence. |
| Comentários internos | — | Notas da equipe sobre o atendimento; o cliente não vê. |
| Histórico | — | Linha do tempo com tudo que aconteceu no card: mudanças de fase, orçamentos, responsável, notas. |

### Montar o orçamento da fórmula (no card)

No bloco "🧪 Orçamentos Fórmula" o atendente monta o(s) orçamento(s) direto no card, sem sair da conversa. Há três caminhos:
1. Processar Receita (IA): no bloco "🔬 Processar Receita", arraste (ou cole) a FOTO ou o PDF da receita e clique em processar — a IA lê a prescrição e monta o orçamento sozinha; depois você só revisa os ativos e a dose.
1. Nova fórmula (manual): abre o editor de orçamento (o mesmo do cap. 6) DENTRO do card, para montar item a item.
1. Importar (texto): botão "📋 Importar" — cola o texto de um orçamento do sistema de origem (formato ORC:…), durante a fase de convivência.

A lista mostra cada orçamento do card com o valor; dá para editar, selecionar e excluir. Botões da seção:

| Campo | Obrig. | Descrição e regra |
| --- | --- | --- |
| 🔗 Associar pendentes | — | Aparece quando um item não foi reconhecido (um sinônimo sem ativo). Abre a associação e salva o sinônimo para as próximas vezes. |
| 🔄 Reprocessar | — | Re-tenta associar os itens pendentes usando os sinônimos atuais e recalcula os valores. |
| 📊 Análise de preços | — | Resumo de margem de TODOS os orçamentos do card (custo, venda, margem em % e R$) e um campo para comparar com o preço do concorrente. |
| 🗑 Excluir selecionados | — | Remove os orçamentos marcados. |

### Enviar o orçamento ao cliente
1. Marque o(s) orçamento(s) e clique em "📤 Enviar WhatsApp".
1. Abre uma PRÉVIA da mensagem para revisão: o resumo da fórmula e os três valores — Valor, Desconto e Valor com desconto. Ajuste o texto se quiser e confirme para enviar pela própria conversa.

**Regra: **A revisão antes do envio é o momento do farmacêutico/atendente conferir a mensagem — nada é enviado ao cliente sem essa confirmação.

### Campos obrigatórios (o checklist da fase)

Em algumas fases (especialmente no Pós-vendas), o card exige campos obrigatórios para avançar — é o checklist de conferência do atendimento.
1. Uma faixa âmbar no card lista os campos que faltam na fase atual; preencha-os na própria ficha (eles salvam sozinhos). Ao responder cada um, ele fica verde.
1. O card NÃO avança de fase, nem fecha como ganho, enquanto houver campo obrigatório vazio.

### Fechar o negócio
1. Ganho: clique em fechar como Ganho. O sistema pede para CONFIRMAR o Valor Final e preencher o checklist; então o card cruza para o funil de Pós-vendas. Nesse momento nascem, automaticamente, a venda no Caixa (cap. 22) e a entrega (cap. 21).
1. Perdido: ao cancelar, é obrigatório informar o MOTIVO (lista pré-definida; "Falta de Insumo" pede qual insumo faltou).
1. Reabrir: um card fechado pode ser reaberto pelo gestor, voltando à fase de origem.

### Pós-venda no card

Depois do ganho, o próprio card conduz a entrega e o pagamento — sem trocar de tela:
1. Aba "🚚 Entrega": tipo (Cliente/Correio/Motoboy/Balcão), endereço (CEP preenche sozinho) e forma de pagamento (cap. 21).
1. Receber pagamento: dá baixa da venda no Caixa direto do card (cap. 22).

**Regra: **Quando o fluxo novo de OM estiver ligado, o card também mostra o painel "🧪 Ordem de Manipulação" para aprovar a formulação e imprimir a ficha de pesagem e o rótulo — ver o capítulo de Produção.

**Regra: **Este capítulo mostra apenas o essencial do card para a rotina da farmácia. A operação comercial completa — atendimento pelo WhatsApp, funil de vendas, automações, fechamento do negócio, pós-vendas e recebimento no Caixa — é detalhada no Manual do TAO Neo (operação da jornada), que é o dono desses capítulos.

# 6. Novo Orçamento

Menu Fórmulas → Novo Orçamento. É a tela mais usada do dia a dia: você monta a fórmula (forma, ativos, dose) e o sistema calcula, ao mesmo tempo, a quantidade a pesar de cada componente e o preço de venda. O orçamento pode nascer aqui (avulso) ou pelo card do cliente no CRM.

![](img/formula-lab/10.png)

*Figura — Editor de Orçamento*

A tela tem quatro blocos, de cima para baixo: (1) quem vai usar, (2) a forma farmacêutica, (3) os ativos da fórmula, (4) embalagens/cápsulas e o preço. Preencha nessa ordem.

### Passo 1 — Quem vai usar (cabeçalho)

| Campo | Obrig. | Descrição e regra |
| --- | --- | --- |
| Paciente | Sim | Quem vai usar a fórmula. Ao digitar o nome, o sistema busca na base de contatos do CRM (a mesma de todo o sistema): navegue com ↑↓ e Enter para escolher — ao selecionar, o WhatsApp é preenchido sozinho. Se a pessoa ainda não existir, digite o nome normalmente. |
| Cliente | — | Só quando quem CONTRATA é diferente de quem usa (ex.: a mãe compra para o filho). Se for a mesma pessoa, deixe em branco. |
| Prescritor | — | Médico/dentista que prescreveu. Busca no cadastro de prescritores por nome ou número. Opcional (obrigatório apenas em controlados). |
| Posologia | — | Como usar (ex.: "1 cápsula ao dia"). Sai no rótulo. Opcional. |

### Passo 2 — A forma farmacêutica

A forma define o tipo de preparação e muda os campos ao lado. Escolha primeiro a forma; os demais campos se ajustam a ela.

| Campo | Obrig. | Descrição e regra |
| --- | --- | --- |
| Forma Farmacêutica | Sim | Cápsula, Creme, Sachê/Envelope, Floral, Solução… Cada forma traz seus parâmetros de cálculo (validade, custo fixo, modo de preparo) já cadastrados (cap. 20). |
| Tipo (Cápsula) | — | Para cápsula, o tipo/número da cápsula (ex.: 0, 1, incolor). Para envelope, a capacidade (5 g / 15 g). |
| Vol / Qtde | Sim | Para líquidos/cremes: o volume total (ex.: 30 ml). Para cápsulas/envelopes: a quantidade de unidades (ex.: 60 cápsulas). |
| Unidade | — | A unidade do campo acima (ml, g, cápsulas, envelopes). |
| Potes | — | Quantos potes/embalagens iguais produzir (multiplica a fórmula). |
| Vol/dose (ml) | — | Para líquidos, o volume de cada dose — usado para calcular o total. |

**Regra: **Ao trocar a forma, o sistema já sugere a embalagem e, no caso de cápsulas/envelopes, o excipiente base (QSP). Confira antes de salvar.

### Passo 3 — Os ativos (a fórmula)
1. Clique em "+ Adicionar Ativo". Digite o nome do princípio ativo e escolha na lista (navegação por teclado ↑↓/Enter). Se o nome prescrito for um sinônimo, o sistema reconhece e associa ao produto certo.
1. Informe a Dose e a Unidade de cada ativo (ex.: 50 mg). O sistema calcula na hora a quantidade a pesar de toda a fórmula (ver "Como o sistema calcula a Quantidade a pesar", cap. 11).
1. Marque QSP na linha do excipiente (o que completa a cápsula/forma) — em cápsulas e envelopes ele já entra automaticamente (Excipiente Base).
1. Repita para cada ativo. Para remover uma linha, use o ✕ ao lado dela.
1. Botão "📋 Fórmula padrão": aplica uma fórmula pronta já cadastrada (forma, cápsula, volume e itens) — útil para preparações repetidas.

**Regra: **Motor v2 ligado (recomendado): a equivalência do sinônimo (sal↔base) ajusta a quantidade automaticamente; uma dose acima do máximo cadastrado fica em vermelho; substância bloqueada/restrita é recusada. Desligado, o cálculo é o simples (só dose × quantidade).

### Passo 4 — Cápsulas e embalagens

Para cápsulas, o bloco "Cápsulas" mostra a cápsula escolhida e o volume — o sistema calcula o número ideal e o custo. O bloco "Embalagem" traz a embalagem sugerida pela forma; você pode trocar ou adicionar ("+ Adicionar Embalagem"). Tudo entra no preço.

### Passo 5 — O preço (quadro de totais)

À direita, o quadro soma tudo automaticamente, de cima para baixo:

| Campo | Obrig. | Descrição e regra |
| --- | --- | --- |
| Valor Calculado | — | Soma dos ativos + embalagens (o "recheio" da fórmula). |
| (+) Custo Fixo da Forma | — | Valor/percentual fixo daquela forma (cadastrado em Formas, cap. 20). |
| (+) Cápsulas | — | Custo das cápsulas. |
| (=) Valor Sub-Total | — | Soma das linhas acima. |
| (+) Acréscimo | — | Um a mais opcional. Digite em % OU em R$ — o outro é calculado sozinho. |
| (=) Valor Sem Desconto | — | Sub-Total + Acréscimo. |
| (–) Desconto | — | Desconto opcional. Também em % OU em R$. |
| Valor Final | Sim | O que o cliente paga = Sem Desconto − Desconto. É o valor que vai ao card e, depois, ao Caixa. |

**Regra: **Acréscimo, Desconto e Custo Fixo trabalham sempre pelo VALOR (R$): você pode digitar o % ou o R$, e o que faltar é derivado. É o Valor Final que aparece no Kanban do card.

### Análise de Preços (margem)

Botão "📊 Análise de Preços": abre, por item, o custo, o preço de venda e a margem (%. e R$). Serve para conferir se o preço está saudável e, se quiser, aplicar uma margem-alvo ao valor final de uma vez. A margem é Preço de Venda ÷ Preço de Custo (usa o preço de compra quando não há custo cadastrado).

### Salvar e enviar
1. Clique em Salvar. Se o orçamento veio de um card, ele fica vinculado ao cliente e o valor aparece no Kanban.
1. O envio ao cliente é feito pelo card do CRM (mensagem de WhatsApp com o resumo: valor, desconto e valor com desconto).

**Regra: **Receita por foto: no card do cliente, o botão de importar receita usa IA para ler a imagem e já montar o orçamento — depois é só revisar aqui os ativos e a dose. O orçamento também pode ser importado do sistema de origem (texto), durante a fase de convivência.

# 7. Histórico e Repetição

Menu Fórmulas → Histórico. Consulta as fórmulas passadas (base sistema de origem 2018–2026) e permite repetir.

![](img/formula-lab/11.png)

*Figura — Tela de Histórico*
1. Digite o nome do cliente (mín. 3 letras); navegue com as setas ↑↓ e Enter.
1. A lista mostra data, resumo dos ativos e valor. Expanda para ver componentes e posologia.
1. Botão "↻ Repetir": recria o orçamento com a mesma forma, cápsula, volume e itens; só a data muda; os preços são recalculados e o valor da última aprovação vai para as observações.

# 8. Estoque — Entrada de NF

Menu Fórmulas → Estoque — Entrada NF. Importa a nota fiscal de compra.

![](img/formula-lab/12.png)

*Figura — Tela de Entrada de NF*
1. Clique em "Carregar" e selecione o arquivo XML da NF-e.
1. O sistema identifica o fornecedor pelo CNPJ; se não existir, cadastre-o em Cotações → Fornecedores com esse CNPJ e recarregue.
1. Na conferência, os itens já vêm associados se aquele fornecedor foi usado antes; os novos, associe ao ativo (a associação é memorizada).
1. Confira o número do lote e a validade de cada item (vêm do XML quando disponíveis; complete o que faltar) — é isso que cria o lote rastreável no estoque.
1. Escolha o destino do valor de cada item: Compra (padrão, atualiza o preço de compra), Custo ou Ambos.
1. Clique em "Efetivar": cria os lotes, lança o estoque, atualiza os preços e gera as contas a pagar.

**Regra: **Depois de efetivar, os lotes entram em quarentena — aprove-os em Lotes (cap. 9) — e as duplicatas da nota vão para Contas a Pagar (cap. 13). O de-para (código do fornecedor → ativo) é aprendido uma vez: nas próximas notas do mesmo fornecedor o item já vem associado.

### Unidades de medida (padronização)

As unidades de compra e venda do ativo saem de um cadastro único (Cadastros → Unidades de Medida, cap. 24) — combo, não texto livre. É o que permite a conversão automática da quantidade da nota para a unidade do estoque (ex.: 1 KG da NF vira 1000 g no lote). Se a NF trouxer uma unidade não cadastrada, o item avisa para você padronizar.

### Importar os laudos da NF (Certificados de Análise)

Ainda na entrada, o botão "📎 Importar laudos da NF" traz os Certificados de Análise dos insumos daquela nota — de uma vez, vários PDFs.
1. Selecione um ou vários PDFs de laudo e clique em "Analisar laudos".
1. O sistema lê cada laudo pelo MOLDE do fornecedor (leitura automática, sem IA — ver cap. 14) e monta a lista de conferência: qual laudo casa com qual lote da NF, com um selo (✅ casou por lote · 🔵 por nome · ⚠ ambíguo · ❌ escolha manual).
1. A farmacêutica confere a lista, ajusta o lote de destino no que estiver ambíguo, e só então clica em "Confirmar e importar" — aí os dados do laudo entram no lote e o PDF fica arquivado.

**Regra: **Nada é gravado antes da confirmação da farmacêutica. É ela quem dá o OK final do casamento laudo → lote.

# 9. Estoque — Lotes

Menu Fórmulas → Estoque — Lotes. É onde se controla a qualidade no recebimento, o saldo por lote, os ajustes e o extrato (kardex) de cada matéria-prima.

![](img/formula-lab/13.png)

*Figura — Tela de Lotes e Saldo*

Cada lote tem número, validade, quantidade e situação (quarentena, aprovado, reprovado ou esgotado). O saldo de um ativo é a soma dos seus lotes aprovados e ainda dentro da validade.

### Controle de qualidade (CQ) no recebimento
1. Lotes novos, vindos da entrada de NF, nascem em "quarentena" — não podem ser usados ainda.
1. Confira o material/laudo e clique em "✔ Aprovar" (ou reprovar, informando o motivo). Só lote APROVADO entra na produção (exigência da RDC 67).
1. Anexe o laudo de análise no lote pelo botão de laudo (📎) — fica arquivado e rastreável (ver cap. 14).

### Saldo, ajuste e kardex
1. A tela mostra o saldo de cada lote; os que vencem em menos de 90 dias aparecem em vermelho.
1. Botão ⚖ (ajuste avulso): informe a quantidade real contada de um lote — o sistema gera o ajuste e registra quem fez e quando. (Para contagem geral, use o Inventário, cap. 15.)
1. Botão ↔ (kardex): abre o extrato de todas as entradas e saídas daquele produto, para conferência.

**Regra: **Só lotes aprovados e dentro da validade aparecem para uso na pesagem da produção — e o de vencimento mais próximo é sugerido primeiro (FEFO).

### Escolha automática do lote (FEFO + lote em uso)

Na produção, o sistema escolhe o lote sozinho — você não precisa selecionar a cada OM. A regra espelha o sistema legado:
1. Prioriza o lote LIBERADO (aprovado) que já está "em uso" (frasco aberto) — para terminar o que já foi aberto antes de abrir outro.
1. Não havendo lote em uso, pega o de VALIDADE mais próxima (FEFO), entre os liberados com saldo.
1. Lote bloqueado/em quarentena nunca é escolhido. O operador ainda pode trocar manualmente, se precisar.

**Regra: **O controle de lote é ligado por produto: no cadastro do ativo (cap. 2) há a opção "Controla lote". Ligada (padrão), o produto é rastreado por lote e entra nessa escolha automática.

# 10. Estoque — Reposição

Menu Fórmulas → Estoque — Reposição. Define mínimos e gera a cotação de compra.

![](img/formula-lab/14.png)

*Figura — Definição de mínimo/máximo/curva de um ativo*

| Campo | Obrig. | Descrição e regra |
| --- | --- | --- |
| Ativo | Sim | Busque o produto a monitorar. |
| Mínimo | Sim | Saldo abaixo do qual o item entra em alerta de reposição. |
| Máximo | — | Nível de reabastecimento; a quantidade sugerida vai até aqui. |
| Curva | — | Classificação ABC (A = mais crítico). |

1. Os itens abaixo do mínimo aparecem destacados em vermelho, com a quantidade sugerida.
1. Marque os itens e clique em "🛒 Gerar cotação" — cria uma cotação no módulo Cotações com os produtos e o último preço pago.

# 11. Produção — Ordem de Manipulação

Menu Fórmulas → Produção. Onde a fórmula é produzida, com rastreabilidade completa.

![](img/formula-lab/15.png)

*Figura — Kanban de produção*
1. Para gerar a OM: busque o orçamento (nº ou paciente) no topo e confirme. A OM nasce com a validade padrão da forma farmacêutica (parâmetro cadastrado em Cadastros → Formas → "Validade padrão (dias)") e já herda o "Modo de preparo" daquela forma.
1. As OMs aparecem no kanban por etapa (Conferência → Pesagem → … → Entregue).
1. Abra a OM: aparece a FICHA DE PESAGEM (ver detalhe abaixo). O sistema JÁ vem com o lote escolhido de cada componente (ver "escolha automática", cap. 9) — o selo "✓ escolhido pelo sistema" indica se foi por FEFO ou por lote em uso. Informe a quantidade pesada de cada componente; troque o lote só se precisar.
1. Confira o "Modo de preparo / precauções" (herdado da forma) e ajuste se esta preparação exigir cuidado específico; Salvar.
1. Mova a OM pelas etapas. Ao concluir (etapa final), o estoque é baixado dos lotes pesados e a VALIDADE é recalculada: passa a ser a MENOR entre o prazo da forma e a validade do lote usado — se o lote reduzir a validade, um alerta é exibido (regra RDC 67 / VALIDADELOTE do sistema de origem).
1. Botão "🖨 Ficha de Pesagem": abre a ficha imprimível para a bancada. Botão "🏷 Rótulo (RDC 67)": abre o rótulo pronto para impressão, já com a validade correta.

### A Ficha de Pesagem — o que orienta o manipulador

É o documento que determina a manipulação: diz exatamente QUAL produto e QUANTO pesar de cada componente. Cada linha traz:

| Campo | Obrig. | Descrição e regra |
| --- | --- | --- |
| Ativo (produto) | Sim | O ATIVO ORIGEM — o produto real que será pesado na balança (ex.: "VALERIANA EXTRATO SECO"). Abaixo dele, em cinza, aparece a "prescrição" (como foi prescrito). |
| Dose prescrita | — | A dose da receita por unidade (ex.: 50 mg) — referência. |
| Qtd a PESAR | Sim | A QUANTIDADE que vai na balança (ex.: 3,5 g), já calculada pelo motor: dose × volume/nº de cápsulas, corrigida pelo teor do lote, pela equivalência sal↔base e pela diluição. As correções aplicadas aparecem embaixo (ex.: "teor 98% · equiv ×1,15"). |
| Lote / validade | Sim | O lote de MP escolhido e sua validade (rastreabilidade). |
| Pesado / Visto | — | Campos em branco para o manipulador registrar o pesado real e o visto. |

**Regra: **REGRA de nomes (importante): a FICHA DE PESAGEM usa o ativo ORIGEM (o produto que se pesa); já o ORÇAMENTO e o RÓTULO usam a DESCRIÇÃO DA PRESCRIÇÃO (o que foi prescrito, muitas vezes um sinônimo — o cliente reconhece). São documentos com públicos diferentes.

**Regra: **Controlados (Portaria 344/98): se a OM tiver componente controlado, aparece o bloco "🔒 Receita controlada" — a OM NÃO conclui sem tipo de receita, nº da notificação, comprador e prescritor. Esses dados alimentam a escrituração automática no SNGPC.

**Regra: **A escolha do lote em cada componente é o que garante a rastreabilidade: lote de MP → OM → paciente. A baixa de estoque é feita uma única vez por OM, no momento da CONCLUSÃO — ao gerar a OM e pesar o insumo, o estoque ainda NÃO cai; só cai quando a OM é concluída (etapa final).

**Regra: **Recálculo pela pesagem do lote (opcional): quando ligada a chave "Recalcular pesagem pelo lote" (Configurações de Fórmulas), a Qtd a Pesar é ajustada pelo teor/fator REAIS do lote escolhido — mostra o "antes → depois" e reflete na ficha. Desligada, usa o teor do cadastro. Não altera o cálculo do orçamento.

![](img/formula-lab/16.png)

*Figura — Exemplo de Ficha de Pesagem — cápsulas, 60 unidades (com as correções de teor, equivalência e diluição)*

### Como o sistema calcula a "Quantidade a pesar"

A coluna "Qtd a PESAR" é o coração da ficha: é exatamente quanto o manipulador coloca na balança. O sistema parte da dose da receita e aplica, quando cadastradas, três correções — teor do lote, equivalência sal↔base e diluição. A conta é:

**Qtd a pesar = (dose × nº de unidades) × equivalência × diluição ÷ (teor ÷ 100)**

• dose × nº de unidades — quantidade nominal do princípio ativo na fórmula inteira;

• equivalência (sal↔base) — fator quando o que foi prescrito e o insumo comprado são formas diferentes da mesma substância;

• diluição (1:N) — fator quando o insumo já vem diluído da fábrica;

• teor (%) — concentração real do princípio ativo no lote; pesa-se mais para compensar o que não é princípio ativo.

Quando não há correção, o fator vale 1 (e o teor, 100%). As correções efetivamente aplicadas aparecem em cinza, logo abaixo da quantidade, na própria ficha.

Exemplos (todos com 60 cápsulas, exatamente como na figura acima):
- **1) Sem correção — Vitamina C: **500 mg × 60 = 30.000 mg = 30 g. Teor 100%, sem equivalência nem diluição.
- **2) Correção de teor — Biotina (teor 95%): **100 mg × 60 = 6.000 mg = 6 g; ÷ 0,95 = 6,32 g. Pesa-se um pouco mais porque o pó tem 95% de princípio ativo.
- **3) Equivalência sal↔base — Propranolol: **prescrito como base: 40 mg × 60 = 2.400 mg = 2,4 g; × 1,15 (fator do sal) = 2,76 g.
- **4) Diluição 1:100 — Melatonina: **0,25 mg × 60 = 15 mg; × 100 = 1.500 mg = 1,5 g do diluído.
- **5) QSP — Excipiente base: **não tem dose fixa: completa o volume interno da cápsula (cálculo por VOLAPA). A ficha mostra "QSP".

**Regra: **A quantidade já vem calculada e conferida no orçamento — a ficha apenas a apresenta para a bancada. O manipulador pesa o valor indicado e anota na coluna "Pesado". Se trocar o lote, a validade final da fórmula é recalculada na conclusão da OM.

### Imprimir a ficha (qualquer impressora)

O botão "🖨 Ficha de Pesagem" (na tela Produção ou no card) abre a ficha em uma nova aba já pronta para impressão, no formato A4. Basta usar o diálogo de impressão do navegador (Ctrl+P) e escolher a impressora — funciona em qualquer modelo (jato de tinta, laser ou multifuncional Epson). A folha já sai com margens corretas e sem cortar as linhas da tabela.

# 12. Livro de Receituário

Menu Fórmulas → Livro de Receituário. Registro legal sequencial de todas as manipulações (Lei 5.991 art. 42 + RDC 67). Toda OM concluída entra automaticamente no livro.

![](img/formula-lab/17.png)

*Figura — Tela do Livro de Receituário*
1. Escolha o período (de/até) e clique em "Gerar".
1. A lista traz, em ordem cronológica, o nº da OM, a data, o paciente, o prescritor, a fórmula, a validade e a situação.
1. Botão "🖨 Imprimir" gera a versão para arquivo ou fiscalização.

**Regra: **O livro é alimentado sozinho pela produção — você não digita nada aqui, apenas consulta e imprime. A numeração é contínua (exigência legal).

# 13. Contas a Pagar

Menu Fórmulas → Contas a Pagar. As duplicatas (parcelas) geradas automaticamente pelas notas fiscais de compra — o controle do que a farmácia deve aos fornecedores.

![](img/formula-lab/18.png)

*Figura — Tela de Contas a Pagar*
1. Filtre por situação (aberto, pagas, todas) e por vencimento.
1. Quando quitar uma parcela, clique em "✔ pagar" (registra a data do pagamento). "Reabrir" desfaz, se lançou errado.
1. Botão "🖨 Relatório (contador)" gera a versão imprimível para a contabilidade.

**Regra: **As parcelas nascem da entrada de NF (cap. 8), conforme o prazo de pagamento do fornecedor. Contas vencidas e ainda em aberto aparecem em vermelho.

# 14. Ficha Técnica da Matéria-Prima e Laudo por Lote

Dois registros exigidos pela RDC 67: a especificação da matéria-prima (na ficha do ativo) e o laudo de análise arquivado por lote.

### Ficha técnica (no ativo)

Em Ativos, abra o ativo → Editar → seção "Ficha técnica da matéria-prima (RDC 67)".

| Campo | Obrig. | Descrição e regra |
| --- | --- | --- |
| Nome químico / Fórmula / Peso molecular | — | Identificação química da substância. |
| Ponto de fusão / pH / Solubilidade | — | Constantes físico-químicas. |
| Caracteres | — | Aspecto, cor e odor (organoléptico). |
| Grau de pureza / teor | — | Faixa de teor aceitável. |
| Conservação / Referências / Revisão | — | Armazenamento, farmacopeia de referência e versão da ficha. |

### Laudo por lote
1. Em Estoque → Lotes, clique no botão de Laudo (📎) do lote.
1. Informe o nº do certificado/laudo e anexe o arquivo (PDF, JPG ou PNG).
1. O ícone passa a 📄; dá para reabrir e ver o laudo a qualquer momento.

**Regra: **Os laudos ficam arquivados e rastreáveis por lote — atende à exigência de guarda do laudo de análise.

### Modelos de Laudo — leitura automática (IA só na 1ª vez)

Menu Fórmulas → Estoque — Modelos de Laudo. Em vez de digitar cada laudo, o sistema APRENDE o layout de cada fornecedor uma única vez e depois lê os laudos sozinho, sem IA.

![](img/formula-lab/19.png)

*Figura — Modelos de Laudo — a IA propõe o layout do fornecedor*
1. Na 1ª vez de um fornecedor: selecione-o, suba um PDF de laudo de exemplo e clique em "Analisar laudo (IA propõe o molde)".
1. A IA identifica os rótulos (produto, lote, validade, fabricante, ensaios…) e monta o molde. Você revisa/ajusta e vê a prévia do que será extraído.
1. Salve. A partir daí, na Entrada de NF (cap. 8), os laudos daquele fornecedor são lidos automaticamente (determinístico), sem IA — só a conferência da farmacêutica.

**Regra: **Um fornecedor pode ter mais de um layout (ex.: extrato vegetal e cápsula): o sistema reconhece cada um pela "assinatura" e usa o molde certo. Um layout novo → um molde novo.

# 15. Estoque — Inventário

Menu Fórmulas → Estoque — Inventário. Contagem geral do estoque com apuração de diferenças em lote (além do ajuste avulso que existe em Lotes).

![](img/formula-lab/20.png)

*Figura — Inventário em massa*
1. Clique em "+ Novo inventário", dê uma descrição e escolha o escopo (só lotes aprovados ou todos com saldo). O saldo atual é congelado como base.
1. Na planilha, digite a quantidade real contada de cada lote. A diferença aparece na hora (verde para sobra, vermelho para falta) e é salva automaticamente. Use a busca para achar o item.
1. Ao terminar, clique em "Fechar inventário": todos os ajustes são aplicados de uma vez (atualiza os lotes e lança os movimentos no kardex). Lotes não contados ficam inalterados.

**Regra: **Dá para cancelar a sessão sem aplicar nada. Ideal para o inventário inicial no corte e para as contagens periódicas.

# 16. Histórico de Preços do Ativo

Em Ativos, abra um ativo e clique em "📈 Histórico de preços". Serve para acompanhar a evolução do custo de um insumo ao longo do tempo.
1. Cada entrada de nota fiscal e cada alteração manual de preço registra um ponto na linha do tempo.
1. A tabela mostra, por data e origem, os valores de compra, custo e venda.

**Regra: **Use para negociar com fornecedores e revisar a margem de venda quando o custo de um insumo sobe.

# 17. Produção Interna

Menu Fórmulas → Produção Interna. Onde a farmácia prepara suas diluições e bases (ex.: Testosterona 1:10), gerando lote próprio com rastreabilidade — em vez de comprar o diluído pronto.

![](img/formula-lab/21.png)

*Figura — Produção interna de diluições*
1. Clique em "+ Nova produção"; escolha o diluído/base a produzir e a quantidade. Se o produto já tem receita vinculada, ela aparece; senão, busque a fórmula.
1. O sistema escala a receita e calcula os insumos proporcionais (o ativo puro + o veículo/excipiente).
1. Na pesagem, escolha o lote FEFO de cada insumo (só aprovados) e informe o pesado — igual à produção de OM.
1. Clique em "Concluir": os insumos são baixados do estoque e é gerado um lote novo do diluído (nº PI-AAAAMM-NNN), já aprovado e disponível para as fórmulas.

**Regra: **O lote produzido guarda o teor, o fator de diluição e o vínculo ao lote da matéria-prima pura consumida (rastreabilidade completa).

# 18. Controlados / SNGPC

Menu Fórmulas → Controlados / SNGPC. Escrituração das substâncias sujeitas a controle especial (Portaria 344/98) e geração do arquivo para a ANVISA.

![](img/formula-lab/22.png)

*Figura — Controlados / SNGPC*

### Marcar as substâncias controladas
1. Em Ativos → Editar, marque "Substância controlada" e informe a classe (A1, A2, A3, B1, B2, C1, AM...). Só assim o sistema passa a tratar aquele ativo como controlado.

### Escriturar e fechar o balanço
1. A tela mostra o livro de movimentos. Use "Lançar" para registrar manualmente uma entrada, saída, perda, transferência ou inventário de uma substância.
1. Quando você conclui uma OM que tem componente controlado, a SAÍDA é escriturada automaticamente — com prescritor, comprador e nº da receita — sem digitação e sem duplicar.
1. "Balanço" mostra o saldo por substância no período (o BSPO). "Gerar XML" produz o arquivo para transmissão à ANVISA.

**Regra: **A transmissão ao webservice da ANVISA ainda é manual: o sistema gera o XML para você enviar pelo portal. Antimicrobianos (AM) só precisam ser escriturados se forem manipulados (a Magis não manipula).

# 19. LGPD e CID-10

Recursos de conformidade e apoio no cadastro do paciente e no orçamento.

### Consentimento e anonimização (LGPD)
1. No cadastro do cliente (Histórico → editar), a seção "Consentimento (LGPD)" registra se o paciente consentiu, a data e o canal (verbal, WhatsApp, formulário, termo).
1. O botão "🔒 Anonimizar" (apenas gestor) apaga os dados pessoais do paciente preservando as OMs por rastreabilidade — atende ao direito ao esquecimento.

**Regra: **Todo acesso aos dados de saúde de um paciente fica registrado numa trilha de auditoria.

### CID-10 no orçamento
1. No editor de orçamento, o campo "CID-10" permite associar o diagnóstico por código ou descrição (autocomplete).

**Regra: **O CID é sempre OPCIONAL — nunca trava o orçamento.

# 20. Formas Farmacêuticas (parâmetros)

Menu Cadastros → Formas Farmacêuticas. Cada forma (Cápsula, Creme, Floral, Envelope…) tem parâmetros próprios que o sistema usa nos cálculos e na produção — nada fica fixo no código, tudo é cadastrado aqui (mesma filosofia dos Parâmetros do sistema de origem).

![](img/formula-lab/23.png)

*Figura — Cadastro da forma — Validade padrão e Modo de preparo*

| Campo | Obrig. | Descrição e regra |
| --- | --- | --- |
| Nome / Tipo | Sim | Identificação e tipo da forma (cápsula, creme, envelope, floral, etc.). |
| Volume / Cápsula | — | Volume-base e, para cápsulas, tipo e número. |
| Custo fixo / Margem | — | Custo fixo da forma (R$ ou %) e margem padrão de venda. |
| Validade padrão (dias) | Sim | Prazo de validade da fórmula manipulada nesta forma (RDC 67). Usado na OM; a validade final será a MENOR entre este prazo e a validade do lote usado. |
| Modo de preparo / precauções | — | Procedimento padrão de manipulação e precauções (RDC 67/BPF). É herdado por toda OM desta forma e pode ser ajustado por receita na produção. |

**Regra: **Cadastre a validade e o modo de preparo de cada forma com a Farmacêutica RT antes de operar — são esses parâmetros que alimentam a validade do rótulo e o procedimento impresso na OM.

# 21. Entregas

Módulo de última milha, integrado ao card do CRM e ao funil Pós-vendas. A entrega é criada automaticamente quando o pedido é aprovado (card ganho).

![](img/formula-lab/24.png)

*Figura — Painel de entregas*

### A entrega no card
1. Ao fechar o card como GANHO (ele vai para o funil Pós-vendas), a Entrega é criada sozinha (status pendente), já vinculada ao cliente.
1. Na aba "🚚 Entrega" do card: escolha o tipo (Cliente = Uber/99/transporte do próprio cliente, Correio, Motoboy ou Balcão/retirada), informe o endereço (digite o CEP para preencher automático, ou escolha um endereço já salvo do cliente) e a forma de pagamento.
1. Acompanhe pelo funil Pós-vendas: Pronto para Entrega → Em Rota → Entregue / Não Entregue → NPS.

### Painel gerencial
1. O menu Entregas abre um painel com os indicadores: entregas por status, custo, valores a receber e recebidos, por tipo e por dia.

**Regra: **Duas travas de operação: (1) o card não avança para "Pronto para Entrega" sem endereço (exceto Balcão/retirada); (2) o card não avança para "NPS" sem o pagamento registrado.

# 22. Caixa (financeiro)

Menu Financeiro → Caixa. Recebe as vendas dos cards ganhos e faz a baixa dos pagamentos, com taxas de cartão, sessão de caixa e conciliação.

![](img/formula-lab/25.png)

*Figura — Caixa — Vendas e recebimento*

### Receber uma venda
1. Cada card ganho gera uma venda "a receber". Em Caixa → Vendas, clique em "Receber": escolha a(s) forma(s) de pagamento (aceita split e parcelas) e confirme — a venda é quitada, com o recibo e a taxa da operadora já calculada.
1. Pagamento na entrega: ao marcar a Entrega como paga (com a forma) na aba do card, a baixa é feita no Caixa automaticamente — e o contrário também: dar baixa no Caixa marca a entrega como paga (libera o NPS).
1. Estorno: em Vendas, o botão "Estornar" (com motivo) reverte o recibo de forma auditada (guarda motivo, quem e quando) e reabre a venda.

### Sessão e conciliação
1. Sessão (Caixa → Sessão): abra o caixa com o saldo inicial e feche no fim do dia — o sistema confere o esperado (dinheiro recebido) com o contado e aponta a diferença.
1. Conciliação (Caixa → Conciliação): confirme o que caiu de cartão/PIX na data prevista e antecipe recebíveis quando precisar (aplica a taxa de antecipação).

**Regra: **As formas de pagamento e as taxas (MDR) por operadora e faixa de parcelas são configuradas em Configuração → TAO Caixa.

# 23. Estoque — Valor do Estoque

Menu Fórmulas → Estoque — Valor do Estoque. A foto financeira do estoque: quanto vale o saldo de cada produto, a custo, a preço de compra e a preço de venda.

![](img/formula-lab/26.png)

*Figura — Valor do Estoque — valorização por produto (editável)*

Cada linha traz o saldo do produto (lotes com estoque) multiplicado pelos seus valores unitários. Cada total bate com o seu unitário: Custo total = custo unit × qtde; Compra total = compra unit × qtde; Venda total = venda unit × qtde.

| Campo | Obrig. | Descrição e regra |
| --- | --- | --- |
| Filtro Grupo | — | Matéria-prima, embalagem ou todos. |
| Filtro Status do lote | — | Todos (estoque físico) · Só liberados · Em quarentena. |
| Custo unit / total | — | Custo de mercado do cadastro. Em cinza quando o custo não foi cadastrado (custo total = 0). |
| Compra unit / total | — | Último preço de compra pago — é a base do "Valor do estoque". |
| Venda unit / total | — | Preço de venda do cadastro. |

1. Os cards do topo somam: Valor a custo, Valor do estoque (a compra), Valor a venda e a Margem potencial.
1. Os valores unitários são EDITÁVEIS direto na tabela — digite e saia do campo; grava no cadastro do ativo e os totais recalculam na hora (verde = salvo).

**Regra: **A tela também "denuncia" preços errados do cadastro (ex.: um item com venda igual ao custo, ou um valor absurdo por grama que deveria ser por litro/frasco) — corrija ali mesmo.

# 24. Estoque — Certificados / Laudos

Menu Fórmulas → Estoque — Certificados / Laudos. Consulta os laudos importados por lote e EMITE o Certificado de Análise da farmácia (RDC 67) a partir do laudo do fornecedor.

![](img/formula-lab/27.png)

*Figura — Certificados / Laudos — consulta e emissão*
1. Busque por ativo, lote ou fabricante; filtre por resultado (Aprovado/Reprovado).
1. Clique em "ver / certificado" para abrir os dados extraídos do laudo (produto, lote, validade, fabricante, ensaios) e o PDF do fornecedor.
1. Botão "📄 Gerar Certificado": emite o Certificado de Análise da farmácia — documento pronto para imprimir/salvar em PDF, com o cabeçalho da farmácia, os dados do lote, os ensaios, a identificação e a assinatura da responsável técnica.

**Regra: **O PDF do fornecedor fica guardado em nuvem com acesso protegido (URL temporária) — atende à LGPD.

# 25. Cadastros — Unidades de Medida

Menu Cadastros → Unidades de Medida. O cadastro único das unidades usadas em compra e venda — é o que padroniza o sistema e alimenta a conversão automática na Entrada de NF.

![](img/formula-lab/28.png)

*Figura — Unidades de Medida — cadastro e fatores*

| Campo | Obrig. | Descrição e regra |
| --- | --- | --- |
| Sigla | Sim | Ex.: KG, G, MG, L, ML, UN, CAP. |
| Dimensão | Sim | Massa, volume ou contagem — a conversão só acontece dentro da mesma dimensão. |
| Fator para a base | Sim | Quanto vale na unidade base da dimensão (massa: G=1, KG=1000; volume: ML=1, L=1000). |

1. Botão "⚙ Criar unidades padrão": semeia de uma vez as unidades comuns que faltarem.

**Regra: **Essas unidades aparecem como combo no cadastro do ativo (compra/venda) e no módulo de Cotações — fim do texto livre, que causava erro de conversão.

# Glossário — termos usados no sistema

Se você é novo na farmácia de manipulação, consulte aqui os termos que aparecem nas telas e neste manual.

| Campo | Obrig. | Descrição e regra |
| --- | --- | --- |
| OM — Ordem de Manipulação | — | Documento que autoriza e orienta a produção de uma fórmula. Nasce do orçamento aprovado. |
| Ficha de Pesagem | — | Impresso da OM que diz qual produto e quanto pesar de cada componente, com o lote usado. |
| Ativo / Matéria-prima (MP) | — | Insumo da fórmula: princípio ativo, excipiente ou base. |
| QSP | — | Latim "quantidade suficiente para". É o excipiente que completa o volume da cápsula/forma. |
| Dose | — | Quantidade do princípio ativo por unidade da fórmula (ex.: 50 mg por cápsula). |
| Teor (%) | — | Concentração real do princípio ativo no lote. Quanto menor o teor, mais se pesa para compensar. |
| Equivalência sal↔base | — | Fator que ajusta a quantidade quando o prescrito e o insumo são formas diferentes da mesma substância (ex.: o sal vs. a base). |
| Diluição (1:N) | — | Insumo que já vem diluído de fábrica (ex.: 1:100). Multiplica a quantidade a pesar. |
| VOLAPA | — | Volume aparente do pó — usado para calcular quanto o excipiente (QSP) completa dentro da cápsula. |
| FEFO | — | First Expire, First Out: usa primeiro o lote de validade mais próxima. |
| Lote | — | Identificação de um recebimento de matéria-prima, com validade e quantidade próprias. Base da rastreabilidade. |
| CQ — Controle de Qualidade | — | Aprovação/reprovação do lote no recebimento. Só lote aprovado entra na produção (RDC 67). |
| Kardex | — | Extrato de todas as entradas e saídas de um produto no estoque. |
| Curva ABC | — | Classificação de importância do item no estoque (A = mais crítico). |
| Card (CRM) | — | O atendimento do cliente no funil de vendas/pós-vendas — onde o pedido começa e é acompanhado até a entrega. |
| RDC 67/2007 | — | Norma da ANVISA para farmácias de manipulação: boas práticas, rótulo e rastreabilidade. |
| Portaria 344/98 | — | Regras para substâncias e medicamentos sob controle especial. |
| SNGPC | — | Sistema Nacional de Gerenciamento de Produtos Controlados: escrituração enviada à ANVISA. |
| DCB | — | Denominação Comum Brasileira — o nome oficial do princípio ativo. |
| RT — Responsável Técnico | — | Farmacêutico responsável pela farmácia; seus dados saem no rótulo. |

