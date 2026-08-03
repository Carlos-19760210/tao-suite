# -*- coding: utf-8 -*-
# Manual do Usuário do TAO Lab em DOCX — detalhado, com prints das telas E dos
# formulários de cadastro + tabelas explicando cada campo e as regras.
import os
from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from manual_cap_card import capitulo_card

PRINTS = os.environ.get("MAN_PRINTS", r"C:\Users\carlo\AppData\Local\Temp\claude\C--Users-carlo\cc1c8537-9fba-44a3-9e06-a41e1fb91f1d\scratchpad\prints")
OUT    = os.environ.get("MAN_OUT", r"C:\Users\carlo\Manual_Usuario_TAO_Lab_v7.docx")
AZUL   = RGBColor(0x1e, 0x40, 0xaf)
CINZA  = RGBColor(0x64, 0x74, 0x8b)

doc = Document()
for s in doc.sections:
    s.top_margin = s.bottom_margin = Cm(1.8); s.left_margin = s.right_margin = Cm(1.8)
st = doc.styles['Normal']; st.font.name = 'Calibri'; st.font.size = Pt(11)

def h1(t):
    p = doc.add_heading(t, level=1); p.runs[0].font.color.rgb = AZUL; return p
def h2(t):
    p = doc.add_heading(t, level=2); p.runs[0].font.color.rgb = AZUL; return p
def h3(t):
    p = doc.add_heading(t, level=3); p.runs[0].font.color.rgb = AZUL; return p
def para(t, italic=False):
    p = doc.add_paragraph(); r = p.add_run(t); r.italic = italic; return p
def passo(t):
    doc.add_paragraph(t, style='List Number')
def nota(t):
    p = doc.add_paragraph(); r = p.add_run('Regra: '); r.bold = True; r.font.color.rgb = AZUL; p.add_run(t)
def imagem(nome, legenda=None, larg=16.8):
    fp = os.path.join(PRINTS, nome + '.png')
    if os.path.isfile(fp):
        doc.add_picture(fp, width=Cm(larg))
        doc.paragraphs[-1].alignment = WD_ALIGN_PARAGRAPH.CENTER
        if legenda:
            c = doc.add_paragraph(); c.alignment = WD_ALIGN_PARAGRAPH.CENTER
            r = c.add_run('Figura — ' + legenda); r.italic = True; r.font.size = Pt(9); r.font.color.rgb = CINZA
def tabela_campos(linhas):
    # linhas: (campo, obrigatorio, descricao)
    t = doc.add_table(rows=1, cols=3); t.style = 'Light Grid Accent 1'
    hdr = t.rows[0].cells
    for i, txt in enumerate(['Campo', 'Obrig.', 'Descrição e regra']):
        hdr[i].paragraphs[0].add_run(txt).bold = True
    for campo, ob, desc in linhas:
        c = t.add_row().cells
        c[0].paragraphs[0].add_run(campo).bold = True
        c[1].text = 'Sim' if ob else '—'
        c[2].text = desc
    # larguras
    for row in t.rows:
        row.cells[0].width = Cm(3.6); row.cells[1].width = Cm(1.6); row.cells[2].width = Cm(11.6)
    doc.add_paragraph()

# ── CAPA ──
t = doc.add_paragraph(); t.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = t.add_run('\n\n\nManual do Usuário'); r.bold = True; r.font.size = Pt(32); r.font.color.rgb = AZUL
s = doc.add_paragraph(); s.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = s.add_run('TAO Lab — Farmácia de Manipulação'); r.font.size = Pt(18)
s2 = doc.add_paragraph(); s2.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = s2.add_run('Cadastros · Orçamento · Estoque · Produção · Laudos · Controlados · RDC 67'); r.font.size = Pt(13); r.font.color.rgb = CINZA
d = doc.add_paragraph(); d.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = d.add_run('\nAcesso: portal solucoesetao.com.br/robos → menu Fórmulas\nVersão v7 — 02/08/2026'); r.font.size = Pt(11)
doc.add_page_break()

h1('Apresentação')
para('Este manual descreve, tela a tela e campo a campo, como operar o TAO Lab — o conjunto de módulos que substitui o sistema de origem na farmácia de manipulação: cadastros, orçamento, entrada de notas fiscais, controle de estoque por lote, produção com rastreabilidade, rótulo e livro de receituário.')
para('As telas funcionam no computador e no celular, pelo portal (solucoesetao.com.br/robos → menu Fórmulas). As imagens deste manual foram capturadas do próprio portal.')
h3('Convenções')
para('• Campos marcados com * (asterisco) são obrigatórios.')
para('• Onde houver "Regra:", trata-se de um comportamento automático ou validação do sistema.')
para('• O cadastro de cliente/paciente é ÚNICO em toda a solução — o mesmo contato do Agente de WhatsApp, do CRM e das Campanhas.', italic=True)
para('• Ícones usados nas telas: 🖨 imprimir · ✔ aprovar/confirmar · ✕ recusar/cancelar · ↻ repetir/reprocessar · ⚖ inventário · ↔ kardex (extrato) · 🛒 gerar cotação · 🔒 controlado.')

# ══════════ PRIMEIROS PASSOS ══════════
doc.add_page_break(); h1('Primeiros passos — acesso e navegação')
para('O TAO Lab é usado pelo navegador (Chrome, Edge ou Firefox), no computador ou no celular — não há nada para instalar.')
h3('Como entrar no sistema')
passo('Abra o endereço solucoesetao.com.br/robos no navegador.')
passo('Digite seu e-mail e senha (fornecidos pelo administrador) e clique em Entrar.')
passo('Você chega à tela inicial do portal. Os módulos da farmácia ficam no menu "Fórmulas"; os cadastros compartilhados, no menu "Cadastros".')
nota('O acesso é liberado por perfil. Se faltar uma tela ou você esquecer a senha, fale com o administrador.')
h3('Como navegar')
para('O menu organiza o sistema em grupos. Os principais para o dia a dia:')
para('• Cadastros — cliente/paciente, prescritores, fornecedores, formas farmacêuticas e produtos/ativos.')
para('• Fórmulas — Novo Orçamento, Histórico, Produção, Estoque (Entrada de NF, Lotes, Reposição, Inventário), Livro de Receituário, Controlados/SNGPC, Contas a Pagar e Configurações.')
para('• Entregas e Caixa — última milha e financeiro (capítulos 21 e 22).')
nota('Em qualquer lista há uma busca no topo. Nos campos que buscam pessoas ou produtos, navegue pelo teclado: setas ↑↓ para percorrer e Enter para escolher.')
h3('Quem faz o quê (perfis)')
para('Cada pessoa vê apenas o que o seu perfil permite (mesmos perfis do TAO Neo): Administrador e Gestor têm acesso completo, inclusive Configurações e Controlados; o perfil Operacional vê o dia a dia (orçamento, produção, estoque). Algumas ações são restritas ao responsável técnico / Gestor: aprovar ou reprovar lote, aprovar orçamento e escriturar controlados.')

# ══════════ VISÃO GERAL DO FLUXO ══════════
doc.add_page_break(); h1('Como o sistema funciona — o fluxo do dia a dia')
para('Antes de entrar tela a tela, veja o caminho que uma fórmula percorre, do pedido do cliente até a entrega. Cada etapa tem um capítulo detalhado adiante — aqui você entende como elas se encaixam.')
passo('CHEGA O PEDIDO. O cliente manda a receita pelo WhatsApp (ou vem pelo balcão). No CRM abre-se um card do atendimento, já vinculado ao contato.')
passo('MONTA-SE O ORÇAMENTO (cap. 6). A partir da receita, escolhe-se a forma farmacêutica e os ativos, com dose e quantidade; o sistema calcula a quantidade a pesar e o preço. O orçamento é enviado ao cliente pelo próprio card.')
passo('CLIENTE APROVA. O card é fechado como "ganho": a venda nasce no Caixa (cap. 22) e a entrega é registrada (cap. 21).')
passo('PRODUÇÃO (cap. 11). Gera-se a Ordem de Manipulação (OM). A FICHA DE PESAGEM orienta o manipulador — qual produto e quanto pesar de cada componente, com o lote (rastreabilidade). Imprimem-se a ficha e o RÓTULO (RDC 67).')
passo('MANIPULA E CONCLUI. O manipulador pesa, registra o lote usado e conclui a OM: o estoque é baixado dos lotes e a validade da fórmula é fixada. Havendo componente controlado, os dados da receita são escriturados no SNGPC (cap. 18).')
passo('ENTREGA E PÓS-VENDA. A fórmula é entregue (cap. 21) e o pagamento é baixado no Caixa. A manipulação fica registrada no Livro de Receituário (cap. 12).')
para('Por trás desse fluxo, o ESTOQUE é alimentado pelas notas fiscais de compra (cap. 8), com controle de qualidade por lote (cap. 9) e reposição automática (cap. 10). E tudo o que o cálculo usa — teor, diluição, validade por forma, dose máxima — é CADASTRADO, não fica fixo no sistema: nos produtos (cap. 2) e nas formas farmacêuticas (cap. 20).')
nota('Em uma linha: Receita → Orçamento → Aprovação → Produção (OM + Ficha + Rótulo) → Conclusão (baixa de estoque + validade + SNGPC) → Entrega + Caixa → Livro. Os cadastros e o estoque sustentam tudo por trás.')

# ══════════ 1. CONFIGURAÇÕES ══════════
doc.add_page_break(); h1('1. Configurações da Farmácia')
para('Menu Fórmulas → Configurações. Ponto de partida: sem estes dados o rótulo não pode ser emitido corretamente.')
imagem('config', 'Tela de Configurações no portal')
h3('Dados da Farmácia')
tabela_campos([
 ('Razão social', True, 'Nome jurídico da farmácia. Sai no rótulo e na NF.'),
 ('Nome fantasia', False, 'Nome comercial (aparece no topo do rótulo).'),
 ('CNPJ', True, 'Somente números. Identifica a farmácia no rótulo e no fiscal.'),
 ('Inscrição Estadual / Municipal', False, 'Para emissão fiscal.'),
 ('Endereço, Bairro, Cidade, UF, CEP', True, 'Endereço completo — obrigatório no rótulo (RDC 67).'),
 ('Telefone / E-mail', False, 'Contato da farmácia.'),
 ('Farmacêutico(a) RT', True, 'Nome do Responsável Técnico. Obrigatório no rótulo.'),
 ('CRF / UF do CRF', True, 'Registro do RT no Conselho. Sai no rótulo.'),
 ('AFE, CEVS, CRF-PJ, Autorização Especial', False, 'Números das licenças sanitárias, para conformidade.'),
])
nota('Clique em "Salvar dados da farmácia". Esses dados alimentam automaticamente todo rótulo emitido.')
h3('Motor farmacotécnico v2')
para('Marque "Ativar no editor de orçamentos" para ligar: equivalência do sinônimo (sal↔base), alerta de dose máxima, trava de substância restrita/bloqueada e uso do teor real do lote no cálculo. Desligado, o cálculo permanece no modo simples. Recomenda-se manter ligado.')

# ══════════ 2. PRODUTOS / ATIVOS ══════════
doc.add_page_break(); h1('2. Produtos / Ativos')
para('Menu Fórmulas → Ativos. Cadastro de matérias-primas (MP) e embalagens.')
imagem('ativos', 'Lista de ativos')
para('Use a busca por nome ou código. Clique no nome de um produto para ver os detalhes e os sinônimos. Para cadastrar, clique em "+ Novo Ativo".')
h3('Buscar e navegar na lista')
passo('Digite parte do nome ou o código na busca do topo — a lista filtra na hora.')
passo('Use o filtro Matéria-Prima / Embalagem para separar os dois tipos; o contador no topo mostra quantos itens existem em cada grupo.')
passo('Ajuste quantos itens ver por página (20/30/50) e percorra as páginas pelo rodapé.')
passo('Clique no nome de um item para ver os detalhes; use o lápis (editar) para alterar; "+ Novo Ativo" para cadastrar.')
h2('Formulário de cadastro do ativo')
imagem('form_ativo', 'Formulário Novo/Editar Ativo')
tabela_campos([
 ('Nome', True, 'Nome do produto (gravado em maiúsculas).'),
 ('Grupo', True, 'Matéria-Prima ou Embalagem.'),
 ('Código', False, 'Código interno (FC). Regra: não pode repetir — se já existir em outro produto, o sistema recusa.'),
 ('Unidade (compra)', False, 'Como o produto é comprado (G, ML, UN).'),
 ('Unidade padrão (venda)', False, 'Unidade usada no orçamento (g, mg, ml, un).'),
 ('Categoria', False, 'Classificação livre (ex.: ativo, excipiente).'),
 ('Preço compra', False, 'Valor pago ao fornecedor. Atualizado automaticamente pela entrada de NF.'),
 ('Custo/unid', False, 'Custo unitário para cálculo de margem.'),
 ('Preço venda', False, 'Preço por unidade usado no orçamento.'),
 ('Markup', False, 'Multiplicador de venda (venda = custo × markup).'),
 ('DCB', False, 'Denominação Comum Brasileira. Usada para dose máxima e identificação.'),
 ('Diluição (1:N)', False, 'Fator de diluição da MP (ex.: 20 = 1:20). Entra no cálculo da pesagem.'),
 ('Teor (%)', False, 'Concentração real do princípio ativo. O motor divide a dose pelo teor.'),
 ('Densidade (g/mL)', False, 'Para líquidos e cálculo de volume em cápsula (VOLAPA).'),
 ('Fator de perda', False, 'Acréscimo por perda de processo (ex.: 1,05 = 5%).'),
 ('Fator de correção', False, 'Correção farmacotécnica adicional (padrão 1).'),
 ('Concentração (UI/UFC/g)', False, 'Para vitaminas/probióticos em UI, UFC ou BLH.'),
 ('Restrição', False, 'Regra: "bloqueada" impede o uso no orçamento; "restrita" apenas alerta. Usado para controle (ex.: GLP-1).'),
 ('Dose máxima / Unid. dose máx', False, 'Regra: com o Motor v2 ligado, dose acima deste valor destaca o campo em vermelho no orçamento.'),
 ('Observações', False, 'Notas livres.'),
])
nota('Os campos técnicos (diluição, teor, densidade, fator de perda) alimentam o cálculo da fórmula no orçamento.')
h3('Aba Sinônimos')
para('Na tela de Ativos, a aba "Sinônimos" guarda os nomes alternativos que o sistema reconhece na prescrição (ex.: a receita diz "Vitamina C" e o produto cadastrado é "ÁCIDO ASCÓRBICO"). Com isso, o orçamento associa o ativo certo mesmo quando o médico usa outro nome.')
passo('Para associar: busque o ativo e vincule o termo alternativo. Quando você associa um ativo no editor de orçamento, o termo digitado já é salvo como sinônimo automaticamente.')
passo('Botão "Somente sem associação": lista os termos que apareceram em orçamentos mas ainda não têm ativo — associe-os para o sistema reconhecê-los da próxima vez.')
passo('Botão "↻ Reprocessar orçamentos": depois de criar um sinônimo, reaplica a associação nos orçamentos que tinham aquele termo em aberto, recalculando os valores.')
h3('Importar catálogo por planilha (gestor)')
para('A aba "📥 Importar planilha" (apenas gestor) carrega o catálogo inteiro de uma vez, a partir de uma planilha modelo (abas Materias-Primas, Embalagens, Tipos-Cápsula).')
passo('Preencha a planilha modelo e clique em "Pré-visualizar" — o sistema valida e mostra o que será criado/atualizado, SEM gravar nada.')
passo('Escolha o modo: "Incremental" (só cria/atualiza o que está na planilha) ou "Completo" (além disso, DESATIVA o que não estiver nela — use só para carga total de um catálogo novo).')
passo('Clique em "Importar agora" para efetivar. O sistema casa por código, preservando os cadastros e sinônimos já existentes.')
nota('Cuidado com o modo "Completo": ele desativa tudo que não estiver na planilha. Para atualizações parciais, use sempre "Incremental".')

# ══════════ 3. PRESCRITORES ══════════
doc.add_page_break(); h1('3. Prescritores')
para('Menu Fórmulas → Prescritores. Médicos, dentistas, veterinários e nutricionistas.')
imagem('prescritores', 'Lista de prescritores')
passo('Busque pelo nome ou número de registro; clique em "+ Novo Prescritor" para cadastrar ou no lápis para editar.')
passo('Ao informar o CEP no cadastro, o endereço (cidade/UF) é preenchido automaticamente.')
h2('Formulário de cadastro do prescritor')
imagem('form_prescritor', 'Formulário Novo Prescritor')
tabela_campos([
 ('Tratamento', False, 'Dr, Dra, etc. Aparece no rótulo antes do nome.'),
 ('Nome', True, 'Nome do prescritor.'),
 ('Tipo registro', False, 'CRM, CRO, CRMV, CRN… (conselho profissional).'),
 ('Nº registro', False, 'Número no conselho.'),
 ('UF registro', False, 'Estado do registro.'),
 ('Especialidade', False, 'Especialidade médica/odontológica.'),
 ('E-mail / Celular / Telefone', False, 'Contatos do prescritor.'),
 ('Endereço / Cidade / UF / CEP', False, 'Endereço do consultório.'),
 ('Observações', False, 'Notas livres.'),
])
nota('No orçamento, o campo Prescritor busca diretamente neste cadastro (por nome ou número). Não é obrigatório informar prescritor no orçamento.')

# ══════════ 4. CLIENTE / PACIENTE ══════════
doc.add_page_break(); h1('4. Cliente / Paciente')
para('O cliente é único em toda a solução (o mesmo do CRM/Agente/Campanha). É gerenciado na tela de Histórico (Fórmulas → Histórico): busque o cliente e use "Editar dados", ou "+ Novo Cliente".')
passo('Para localizar: digite o nome (mín. 3 letras) ou o WhatsApp na busca; use "Editar dados" para atualizar ou "+ Novo Cliente" para cadastrar.')
passo('Antes de criar um novo, confira se a pessoa já existe pelo WhatsApp — o cadastro é único e não deve ser duplicado.')
imagem('form_cliente', 'Formulário Novo/Editar Cliente')
tabela_campos([
 ('Nome', True, 'Nome do paciente.'),
 ('Sexo', False, 'Feminino / Masculino.'),
 ('Nascimento', False, 'Data de nascimento.'),
 ('WhatsApp', True, 'Regra: é a CHAVE ÚNICA do cadastro. Se já existir um contato com esse número, o sistema reaproveita o mesmo (não duplica a pessoa).'),
 ('E-mail', False, 'E-mail do cliente.'),
 ('Características de saúde', False, 'Marcadores comuns: Obesidade, Colesterol, Pressão, Diabetes. Aparecem como selo ao abrir o cliente.'),
 ('Alergias', False, 'Texto livre — destacado no atendimento.'),
 ('Observações', False, 'Notas livres.'),
])
nota('Ao salvar, os dados valem para o CRM, o Agente e as Campanhas — é o mesmo contato. Não crie cadastros paralelos.')

# ══════════ 5. FORNECEDORES ══════════
doc.add_page_break(); h1('5. Fornecedores')
para('Menu Cadastros → Fornecedores (também acessível por Fórmulas → Fornecedores — é o MESMO cadastro). É um cadastro ÚNICO de fornecedor, compartilhado por Fórmula e Cotações (mesma base) — essencial para casar as notas fiscais de compra e para a qualificação exigida pela RDC 67. A lista já traz os fornecedores criados automaticamente pelas notas; aqui você completa os dados.')
imagem('fornecedores', 'Lista de fornecedores')
passo('Busque pelo nome ou CNPJ; "+ Novo Fornecedor" para cadastrar, lápis para editar.')
passo('Preencha o CNPJ antes de importar a primeira nota daquele fornecedor — é o que casa o XML da NF ao cadastro. (Na Entrada de NF, se o CNPJ do emitente ainda não estiver cadastrado, há o botão "Cadastrar fornecedor com os dados da NF", que preenche tudo pelo XML.)')
h3('Campos do cadastro')
imagem('form_fornecedor', 'Formulário Novo/Editar Fornecedor')
tabela_campos([
 ('Tipo pessoa / Nome', True, 'PJ ou PF e o nome/apelido do fornecedor (obrigatório).'),
 ('Razão social / Fantasia', False, 'Nome oficial e fantasia (aparecem na NF).'),
 ('CNPJ / CPF', False, 'Documento — é a CHAVE que casa a NF de compra ao fornecedor. Preencha antes de importar a primeira nota.'),
 ('WhatsApp / Telefone / E-mail', False, 'Contatos para pedidos e cotações (WhatsApp só dígitos, com DDD).'),
 ('Pessoa de contato', False, 'Nome do vendedor/atendente.'),
 ('Tipo', False, 'Fabricante, distribuidor, importador ou transportadora.'),
 ('Regime (CRT)', False, 'Simples Nacional, Simples excesso ou Regime Normal.'),
 ('Inscrições / SUFRAMA / MAPA', False, 'Inscrição estadual, municipal e registros especiais.'),
 ('Endereço / Cidade / UF / CEP', False, 'Endereço completo do fornecedor.'),
 ('Prazo de pagamento', False, 'Ex.: 28/35/42 dias, boleto — usado para gerar as duplicatas em Contas a Pagar.'),
 ('AFE + validade', False, 'Autorização de Funcionamento (ANVISA) e sua validade (RDC 67).'),
 ('Autorização Especial + validade', False, 'AE para fornecer controlados (Portaria 344/98).'),
 ('Licença/Alvará VISA + validade', False, 'Licença sanitária estadual/municipal.'),
 ('Qualificado / data / avaliador', False, 'Marcação da qualificação do fornecedor (RDC 67).'),
 ('Observações', False, 'Pedido mínimo, prazo de entrega, etc.'),
])
nota('Na lista, licenças (AFE/VISA) vencidas aparecem com selo vermelho, e a Entrada de NF exibe um aviso de conformidade no recebimento. Sem o CNPJ cadastrado, a NF não vincula ao fornecedor.')

# ══════════ O CARD DO CLIENTE (capítulo reutilizável — Lab e CRM) ══════════
# Fica JUNTO da seção de orçamentos/manipulação (é no card que o orçamento nasce e a produção é acompanhada).
_H = { 'h1': h1, 'h2': h2, 'h3': h3, 'para': para, 'passo': passo, 'nota': nota,
       'tabela_campos': tabela_campos, 'imagem': imagem, 'page_break': lambda: doc.add_page_break() }
capitulo_card(_H, incluir_formula=True)
nota('Este capítulo mostra apenas o essencial do card para a rotina da farmácia. A operação comercial completa — atendimento pelo WhatsApp, funil de vendas, automações, fechamento do negócio, pós-vendas e recebimento no Caixa — é detalhada no Manual do TAO Neo (operação da jornada), que é o dono desses capítulos.')

# ══════════ 6. NOVO ORÇAMENTO ══════════
doc.add_page_break(); h1('6. Novo Orçamento')
para('Menu Fórmulas → Novo Orçamento. É a tela mais usada do dia a dia: você monta a fórmula (forma, ativos, dose) e o sistema calcula, ao mesmo tempo, a quantidade a pesar de cada componente e o preço de venda. O orçamento pode nascer aqui (avulso) ou pelo card do cliente no CRM.')
imagem('orc_novo', 'Editor de Orçamento')
para('A tela tem quatro blocos, de cima para baixo: (1) quem vai usar, (2) a forma farmacêutica, (3) os ativos da fórmula, (4) embalagens/cápsulas e o preço. Preencha nessa ordem.')

h3('Passo 1 — Quem vai usar (cabeçalho)')
tabela_campos([
 ('Paciente', True, 'Quem vai usar a fórmula. Ao digitar o nome, o sistema busca na base de contatos do CRM (a mesma de todo o sistema): navegue com ↑↓ e Enter para escolher — ao selecionar, o WhatsApp é preenchido sozinho. Se a pessoa ainda não existir, digite o nome normalmente.'),
 ('Cliente', False, 'Só quando quem CONTRATA é diferente de quem usa (ex.: a mãe compra para o filho). Se for a mesma pessoa, deixe em branco.'),
 ('Prescritor', False, 'Médico/dentista que prescreveu. Busca no cadastro de prescritores por nome ou número. Opcional (obrigatório apenas em controlados).'),
 ('Posologia', False, 'Como usar (ex.: "1 cápsula ao dia"). Sai no rótulo. Opcional.'),
])

h3('Passo 2 — A forma farmacêutica')
para('A forma define o tipo de preparação e muda os campos ao lado. Escolha primeiro a forma; os demais campos se ajustam a ela.')
tabela_campos([
 ('Forma Farmacêutica', True, 'Cápsula, Creme, Sachê/Envelope, Floral, Solução… Cada forma traz seus parâmetros de cálculo (validade, custo fixo, modo de preparo) já cadastrados (cap. 20).'),
 ('Tipo (Cápsula)', False, 'Para cápsula, o tipo/número da cápsula (ex.: 0, 1, incolor). Para envelope, a capacidade (5 g / 15 g).'),
 ('Vol / Qtde', True, 'Para líquidos/cremes: o volume total (ex.: 30 ml). Para cápsulas/envelopes: a quantidade de unidades (ex.: 60 cápsulas).'),
 ('Unidade', False, 'A unidade do campo acima (ml, g, cápsulas, envelopes).'),
 ('Potes', False, 'Quantos potes/embalagens iguais produzir (multiplica a fórmula).'),
 ('Vol/dose (ml)', False, 'Para líquidos, o volume de cada dose — usado para calcular o total.'),
])
nota('Ao trocar a forma, o sistema já sugere a embalagem e, no caso de cápsulas/envelopes, o excipiente base (QSP). Confira antes de salvar.')

h3('Passo 3 — Os ativos (a fórmula)')
passo('Clique em "+ Adicionar Ativo". Digite o nome do princípio ativo e escolha na lista (navegação por teclado ↑↓/Enter). Se o nome prescrito for um sinônimo, o sistema reconhece e associa ao produto certo.')
passo('Informe a Dose e a Unidade de cada ativo (ex.: 50 mg). O sistema calcula na hora a quantidade a pesar de toda a fórmula (ver "Como o sistema calcula a Quantidade a pesar", cap. 11).')
passo('Marque QSP na linha do excipiente (o que completa a cápsula/forma) — em cápsulas e envelopes ele já entra automaticamente (Excipiente Base).')
passo('Repita para cada ativo. Para remover uma linha, use o ✕ ao lado dela.')
passo('Botão "📋 Fórmula padrão": aplica uma fórmula pronta já cadastrada (forma, cápsula, volume e itens) — útil para preparações repetidas.')
nota('Motor v2 ligado (recomendado): a equivalência do sinônimo (sal↔base) ajusta a quantidade automaticamente; uma dose acima do máximo cadastrado fica em vermelho; substância bloqueada/restrita é recusada. Desligado, o cálculo é o simples (só dose × quantidade).')

h3('Passo 4 — Cápsulas e embalagens')
para('Para cápsulas, o bloco "Cápsulas" mostra a cápsula escolhida e o volume — o sistema calcula o número ideal e o custo. O bloco "Embalagem" traz a embalagem sugerida pela forma; você pode trocar ou adicionar ("+ Adicionar Embalagem"). Tudo entra no preço.')

h3('Passo 5 — O preço (quadro de totais)')
para('À direita, o quadro soma tudo automaticamente, de cima para baixo:')
tabela_campos([
 ('Valor Calculado', False, 'Soma dos ativos + embalagens (o "recheio" da fórmula).'),
 ('(+) Custo Fixo da Forma', False, 'Valor/percentual fixo daquela forma (cadastrado em Formas, cap. 20).'),
 ('(+) Cápsulas', False, 'Custo das cápsulas.'),
 ('(=) Valor Sub-Total', False, 'Soma das linhas acima.'),
 ('(+) Acréscimo', False, 'Um a mais opcional. Digite em % OU em R$ — o outro é calculado sozinho.'),
 ('(=) Valor Sem Desconto', False, 'Sub-Total + Acréscimo.'),
 ('(–) Desconto', False, 'Desconto opcional. Também em % OU em R$.'),
 ('Valor Final', True, 'O que o cliente paga = Sem Desconto − Desconto. É o valor que vai ao card e, depois, ao Caixa.'),
])
nota('Acréscimo, Desconto e Custo Fixo trabalham sempre pelo VALOR (R$): você pode digitar o % ou o R$, e o que faltar é derivado. É o Valor Final que aparece no Kanban do card.')

h3('Análise de Preços (margem)')
para('Botão "📊 Análise de Preços": abre, por item, o custo, o preço de venda e a margem (%. e R$). Serve para conferir se o preço está saudável e, se quiser, aplicar uma margem-alvo ao valor final de uma vez. A margem é Preço de Venda ÷ Preço de Custo (usa o preço de compra quando não há custo cadastrado).')

h3('Salvar e enviar')
passo('Clique em Salvar. Se o orçamento veio de um card, ele fica vinculado ao cliente e o valor aparece no Kanban.')
passo('O envio ao cliente é feito pelo card do CRM (mensagem de WhatsApp com o resumo: valor, desconto e valor com desconto).')
nota('Receita por foto: no card do cliente, o botão de importar receita usa IA para ler a imagem e já montar o orçamento — depois é só revisar aqui os ativos e a dose. O orçamento também pode ser importado do sistema de origem (texto), durante a fase de convivência.')

# ══════════ 7. HISTÓRICO E REPETIÇÃO ══════════
doc.add_page_break(); h1('7. Histórico e Repetição')
para('Menu Fórmulas → Histórico. Consulta as fórmulas passadas (base sistema de origem 2018–2026) e permite repetir.')
imagem('historico', 'Tela de Histórico')
passo('Digite o nome do cliente (mín. 3 letras); navegue com as setas ↑↓ e Enter.')
passo('A lista mostra data, resumo dos ativos e valor. Expanda para ver componentes e posologia.')
passo('Botão "↻ Repetir": recria o orçamento com a mesma forma, cápsula, volume e itens; só a data muda; os preços são recalculados e o valor da última aprovação vai para as observações.')

# ══════════ 8. ESTOQUE — ENTRADA DE NF ══════════
doc.add_page_break(); h1('8. Estoque — Entrada de NF')
para('Menu Fórmulas → Estoque — Entrada NF. Importa a nota fiscal de compra.')
imagem('estoque_nf', 'Tela de Entrada de NF')
passo('Clique em "Carregar" e selecione o arquivo XML da NF-e.')
passo('O sistema identifica o fornecedor pelo CNPJ; se não existir, cadastre-o em Cotações → Fornecedores com esse CNPJ e recarregue.')
passo('Na conferência, os itens já vêm associados se aquele fornecedor foi usado antes; os novos, associe ao ativo (a associação é memorizada).')
passo('Confira o número do lote e a validade de cada item (vêm do XML quando disponíveis; complete o que faltar) — é isso que cria o lote rastreável no estoque.')
passo('Escolha o destino do valor de cada item: Compra (padrão, atualiza o preço de compra), Custo ou Ambos.')
passo('Clique em "Efetivar": cria os lotes, lança o estoque, atualiza os preços e gera as contas a pagar.')
nota('Depois de efetivar, os lotes entram em quarentena — aprove-os em Lotes (cap. 9) — e as duplicatas da nota vão para Contas a Pagar (cap. 13). O de-para (código do fornecedor → ativo) é aprendido uma vez: nas próximas notas do mesmo fornecedor o item já vem associado.')
h3('Unidades de medida (padronização)')
para('As unidades de compra e venda do ativo saem de um cadastro único (Cadastros → Unidades de Medida, cap. 24) — combo, não texto livre. É o que permite a conversão automática da quantidade da nota para a unidade do estoque (ex.: 1 KG da NF vira 1000 g no lote). Se a NF trouxer uma unidade não cadastrada, o item avisa para você padronizar.')
h3('Importar os laudos da NF (Certificados de Análise)')
para('Ainda na entrada, o botão "📎 Importar laudos da NF" traz os Certificados de Análise dos insumos daquela nota — de uma vez, vários PDFs.')
passo('Selecione um ou vários PDFs de laudo e clique em "Analisar laudos".')
passo('O sistema lê cada laudo pelo MOLDE do fornecedor (leitura automática, sem IA — ver cap. 14) e monta a lista de conferência: qual laudo casa com qual lote da NF, com um selo (✅ casou por lote · 🔵 por nome · ⚠ ambíguo · ❌ escolha manual).')
passo('A farmacêutica confere a lista, ajusta o lote de destino no que estiver ambíguo, e só então clica em "Confirmar e importar" — aí os dados do laudo entram no lote e o PDF fica arquivado.')
nota('Nada é gravado antes da confirmação da farmacêutica. É ela quem dá o OK final do casamento laudo → lote.')

# ══════════ 9. ESTOQUE — LOTES ══════════
doc.add_page_break(); h1('9. Estoque — Lotes')
para('Menu Fórmulas → Estoque — Lotes. É onde se controla a qualidade no recebimento, o saldo por lote, os ajustes e o extrato (kardex) de cada matéria-prima.')
imagem('estoque_lotes', 'Tela de Lotes e Saldo')
para('Cada lote tem número, validade, quantidade e situação (quarentena, aprovado, reprovado ou esgotado). O saldo de um ativo é a soma dos seus lotes aprovados e ainda dentro da validade.')
h3('Controle de qualidade (CQ) no recebimento')
passo('Lotes novos, vindos da entrada de NF, nascem em "quarentena" — não podem ser usados ainda.')
passo('Confira o material/laudo e clique em "✔ Aprovar" (ou reprovar, informando o motivo). Só lote APROVADO entra na produção (exigência da RDC 67).')
passo('Anexe o laudo de análise no lote pelo botão de laudo (📎) — fica arquivado e rastreável (ver cap. 14).')
h3('Saldo, ajuste e kardex')
passo('A tela mostra o saldo de cada lote; os que vencem em menos de 90 dias aparecem em vermelho.')
passo('Botão ⚖ (ajuste avulso): informe a quantidade real contada de um lote — o sistema gera o ajuste e registra quem fez e quando. (Para contagem geral, use o Inventário, cap. 15.)')
passo('Botão ↔ (kardex): abre o extrato de todas as entradas e saídas daquele produto, para conferência.')
nota('Só lotes aprovados e dentro da validade aparecem para uso na pesagem da produção — e o de vencimento mais próximo é sugerido primeiro (FEFO).')
h3('Escolha automática do lote (FEFO + lote em uso)')
para('Na produção, o sistema escolhe o lote sozinho — você não precisa selecionar a cada OM. A regra espelha o sistema legado:')
passo('Prioriza o lote LIBERADO (aprovado) que já está "em uso" (frasco aberto) — para terminar o que já foi aberto antes de abrir outro.')
passo('Não havendo lote em uso, pega o de VALIDADE mais próxima (FEFO), entre os liberados com saldo.')
passo('Lote bloqueado/em quarentena nunca é escolhido. O operador ainda pode trocar manualmente, se precisar.')
nota('O controle de lote é ligado por produto: no cadastro do ativo (cap. 2) há a opção "Controla lote". Ligada (padrão), o produto é rastreado por lote e entra nessa escolha automática.')

# ══════════ 10. ESTOQUE — REPOSIÇÃO ══════════
doc.add_page_break(); h1('10. Estoque — Reposição')
para('Menu Fórmulas → Estoque — Reposição. Define mínimos e gera a cotação de compra.')
imagem('form_reposicao', 'Definição de mínimo/máximo/curva de um ativo')
tabela_campos([
 ('Ativo', True, 'Busque o produto a monitorar.'),
 ('Mínimo', True, 'Saldo abaixo do qual o item entra em alerta de reposição.'),
 ('Máximo', False, 'Nível de reabastecimento; a quantidade sugerida vai até aqui.'),
 ('Curva', False, 'Classificação ABC (A = mais crítico).'),
])
passo('Os itens abaixo do mínimo aparecem destacados em vermelho, com a quantidade sugerida.')
passo('Marque os itens e clique em "🛒 Gerar cotação" — cria uma cotação no módulo Cotações com os produtos e o último preço pago.')

# ══════════ 11. PRODUÇÃO ══════════
doc.add_page_break(); h1('11. Produção — Ordem de Manipulação')
para('Menu Fórmulas → Produção. Onde a fórmula é produzida, com rastreabilidade completa.')
imagem('producao', 'Kanban de produção')
passo('Para gerar a OM: busque o orçamento (nº ou paciente) no topo e confirme. A OM nasce com a validade padrão da forma farmacêutica (parâmetro cadastrado em Cadastros → Formas → "Validade padrão (dias)") e já herda o "Modo de preparo" daquela forma.')
passo('As OMs aparecem no kanban por etapa (Conferência → Pesagem → … → Entregue).')
passo('Abra a OM: aparece a FICHA DE PESAGEM (ver detalhe abaixo). O sistema JÁ vem com o lote escolhido de cada componente (ver "escolha automática", cap. 9) — o selo "✓ escolhido pelo sistema" indica se foi por FEFO ou por lote em uso. Informe a quantidade pesada de cada componente; troque o lote só se precisar.')
passo('Confira o "Modo de preparo / precauções" (herdado da forma) e ajuste se esta preparação exigir cuidado específico; Salvar.')
passo('Mova a OM pelas etapas. Ao concluir (etapa final), o estoque é baixado dos lotes pesados e a VALIDADE é recalculada: passa a ser a MENOR entre o prazo da forma e a validade do lote usado — se o lote reduzir a validade, um alerta é exibido (regra RDC 67 / VALIDADELOTE do sistema de origem).')
passo('Botão "🖨 Ficha de Pesagem": abre a ficha imprimível para a bancada. Botão "🏷 Rótulo (RDC 67)": abre o rótulo pronto para impressão, já com a validade correta.')

h3('A Ficha de Pesagem — o que orienta o manipulador')
para('É o documento que determina a manipulação: diz exatamente QUAL produto e QUANTO pesar de cada componente. Cada linha traz:')
tabela_campos([
 ('Ativo (produto)', True, 'O ATIVO ORIGEM — o produto real que será pesado na balança (ex.: "VALERIANA EXTRATO SECO"). Abaixo dele, em cinza, aparece a "prescrição" (como foi prescrito).'),
 ('Dose prescrita', False, 'A dose da receita por unidade (ex.: 50 mg) — referência.'),
 ('Qtd a PESAR', True, 'A QUANTIDADE que vai na balança (ex.: 3,5 g), já calculada pelo motor: dose × volume/nº de cápsulas, corrigida pelo teor do lote, pela equivalência sal↔base e pela diluição. As correções aplicadas aparecem embaixo (ex.: "teor 98% · equiv ×1,15").'),
 ('Lote / validade', True, 'O lote de MP escolhido e sua validade (rastreabilidade).'),
 ('Pesado / Visto', False, 'Campos em branco para o manipulador registrar o pesado real e o visto.'),
])
nota('REGRA de nomes (importante): a FICHA DE PESAGEM usa o ativo ORIGEM (o produto que se pesa); já o ORÇAMENTO e o RÓTULO usam a DESCRIÇÃO DA PRESCRIÇÃO (o que foi prescrito, muitas vezes um sinônimo — o cliente reconhece). São documentos com públicos diferentes.')
nota('Controlados (Portaria 344/98): se a OM tiver componente controlado, aparece o bloco "🔒 Receita controlada" — a OM NÃO conclui sem tipo de receita, nº da notificação, comprador e prescritor. Esses dados alimentam a escrituração automática no SNGPC.')
nota('A escolha do lote em cada componente é o que garante a rastreabilidade: lote de MP → OM → paciente. A baixa de estoque é feita uma única vez por OM, no momento da CONCLUSÃO — ao gerar a OM e pesar o insumo, o estoque ainda NÃO cai; só cai quando a OM é concluída (etapa final).')
nota('Recálculo pela pesagem do lote (opcional): quando ligada a chave "Recalcular pesagem pelo lote" (Configurações de Fórmulas), a Qtd a Pesar é ajustada pelo teor/fator REAIS do lote escolhido — mostra o "antes → depois" e reflete na ficha. Desligada, usa o teor do cadastro. Não altera o cálculo do orçamento.')

imagem('ficha_pesagem_exemplo', 'Exemplo de Ficha de Pesagem — cápsulas, 60 unidades (com as correções de teor, equivalência e diluição)')

h3('Como o sistema calcula a "Quantidade a pesar"')
para('A coluna "Qtd a PESAR" é o coração da ficha: é exatamente quanto o manipulador coloca na balança. O sistema parte da dose da receita e aplica, quando cadastradas, três correções — teor do lote, equivalência sal↔base e diluição. A conta é:')
_pf = doc.add_paragraph(); _pf.alignment = WD_ALIGN_PARAGRAPH.CENTER
_rf = _pf.add_run('Qtd a pesar = (dose × nº de unidades) × equivalência × diluição ÷ (teor ÷ 100)')
_rf.bold = True; _rf.font.color.rgb = AZUL
para('• dose × nº de unidades — quantidade nominal do princípio ativo na fórmula inteira;')
para('• equivalência (sal↔base) — fator quando o que foi prescrito e o insumo comprado são formas diferentes da mesma substância;')
para('• diluição (1:N) — fator quando o insumo já vem diluído da fábrica;')
para('• teor (%) — concentração real do princípio ativo no lote; pesa-se mais para compensar o que não é princípio ativo.')
para('Quando não há correção, o fator vale 1 (e o teor, 100%). As correções efetivamente aplicadas aparecem em cinza, logo abaixo da quantidade, na própria ficha.')
para('Exemplos (todos com 60 cápsulas, exatamente como na figura acima):')
for _tit, _conta in [
    ('1) Sem correção — Vitamina C', '500 mg × 60 = 30.000 mg = 30 g. Teor 100%, sem equivalência nem diluição.'),
    ('2) Correção de teor — Biotina (teor 95%)', '100 mg × 60 = 6.000 mg = 6 g; ÷ 0,95 = 6,32 g. Pesa-se um pouco mais porque o pó tem 95% de princípio ativo.'),
    ('3) Equivalência sal↔base — Propranolol', 'prescrito como base: 40 mg × 60 = 2.400 mg = 2,4 g; × 1,15 (fator do sal) = 2,76 g.'),
    ('4) Diluição 1:100 — Melatonina', '0,25 mg × 60 = 15 mg; × 100 = 1.500 mg = 1,5 g do diluído.'),
    ('5) QSP — Excipiente base', 'não tem dose fixa: completa o volume interno da cápsula (cálculo por VOLAPA). A ficha mostra "QSP".'),
]:
    _pp = doc.add_paragraph(style='List Bullet'); _rr = _pp.add_run(_tit + ': '); _rr.bold = True; _pp.add_run(_conta)
nota('A quantidade já vem calculada e conferida no orçamento — a ficha apenas a apresenta para a bancada. O manipulador pesa o valor indicado e anota na coluna "Pesado". Se trocar o lote, a validade final da fórmula é recalculada na conclusão da OM.')

h3('Imprimir a ficha (qualquer impressora)')
para('O botão "🖨 Ficha de Pesagem" (na tela Produção ou no card) abre a ficha em uma nova aba já pronta para impressão, no formato A4. Basta usar o diálogo de impressão do navegador (Ctrl+P) e escolher a impressora — funciona em qualquer modelo (jato de tinta, laser ou multifuncional Epson). A folha já sai com margens corretas e sem cortar as linhas da tabela.')

# ══════════ 12. LIVRO DE RECEITUÁRIO ══════════
doc.add_page_break(); h1('12. Livro de Receituário')
para('Menu Fórmulas → Livro de Receituário. Registro legal sequencial de todas as manipulações (Lei 5.991 art. 42 + RDC 67). Toda OM concluída entra automaticamente no livro.')
imagem('livro', 'Tela do Livro de Receituário')
passo('Escolha o período (de/até) e clique em "Gerar".')
passo('A lista traz, em ordem cronológica, o nº da OM, a data, o paciente, o prescritor, a fórmula, a validade e a situação.')
passo('Botão "🖨 Imprimir" gera a versão para arquivo ou fiscalização.')
nota('O livro é alimentado sozinho pela produção — você não digita nada aqui, apenas consulta e imprime. A numeração é contínua (exigência legal).')

# ══════════ 13. CONTAS A PAGAR ══════════
doc.add_page_break(); h1('13. Contas a Pagar')
para('Menu Fórmulas → Contas a Pagar. As duplicatas (parcelas) geradas automaticamente pelas notas fiscais de compra — o controle do que a farmácia deve aos fornecedores.')
imagem('contas_pagar', 'Tela de Contas a Pagar')
passo('Filtre por situação (aberto, pagas, todas) e por vencimento.')
passo('Quando quitar uma parcela, clique em "✔ pagar" (registra a data do pagamento). "Reabrir" desfaz, se lançou errado.')
passo('Botão "🖨 Relatório (contador)" gera a versão imprimível para a contabilidade.')
nota('As parcelas nascem da entrada de NF (cap. 8), conforme o prazo de pagamento do fornecedor. Contas vencidas e ainda em aberto aparecem em vermelho.')

# ══════════ 15→14. FICHA TÉCNICA + LAUDO POR LOTE (RDC 67) ══════════
doc.add_page_break(); h1('14. Ficha Técnica da Matéria-Prima e Laudo por Lote')
para('Dois registros exigidos pela RDC 67: a especificação da matéria-prima (na ficha do ativo) e o laudo de análise arquivado por lote.')
h3('Ficha técnica (no ativo)')
para('Em Ativos, abra o ativo → Editar → seção "Ficha técnica da matéria-prima (RDC 67)".')
tabela_campos([
    ('Nome químico / Fórmula / Peso molecular', False, 'Identificação química da substância.'),
    ('Ponto de fusão / pH / Solubilidade', False, 'Constantes físico-químicas.'),
    ('Caracteres', False, 'Aspecto, cor e odor (organoléptico).'),
    ('Grau de pureza / teor', False, 'Faixa de teor aceitável.'),
    ('Conservação / Referências / Revisão', False, 'Armazenamento, farmacopeia de referência e versão da ficha.'),
])
h3('Laudo por lote')
passo('Em Estoque → Lotes, clique no botão de Laudo (📎) do lote.')
passo('Informe o nº do certificado/laudo e anexe o arquivo (PDF, JPG ou PNG).')
passo('O ícone passa a 📄; dá para reabrir e ver o laudo a qualquer momento.')
nota('Os laudos ficam arquivados e rastreáveis por lote — atende à exigência de guarda do laudo de análise.')

h3('Modelos de Laudo — leitura automática (IA só na 1ª vez)')
para('Menu Fórmulas → Estoque — Modelos de Laudo. Em vez de digitar cada laudo, o sistema APRENDE o layout de cada fornecedor uma única vez e depois lê os laudos sozinho, sem IA.')
imagem('laudo_modelos', 'Modelos de Laudo — a IA propõe o layout do fornecedor')
passo('Na 1ª vez de um fornecedor: selecione-o, suba um PDF de laudo de exemplo e clique em "Analisar laudo (IA propõe o molde)".')
passo('A IA identifica os rótulos (produto, lote, validade, fabricante, ensaios…) e monta o molde. Você revisa/ajusta e vê a prévia do que será extraído.')
passo('Salve. A partir daí, na Entrada de NF (cap. 8), os laudos daquele fornecedor são lidos automaticamente (determinístico), sem IA — só a conferência da farmacêutica.')
nota('Um fornecedor pode ter mais de um layout (ex.: extrato vegetal e cápsula): o sistema reconhece cada um pela "assinatura" e usa o molde certo. Um layout novo → um molde novo.')

# ══════════ 16. INVENTÁRIO EM MASSA ══════════
doc.add_page_break(); h1('15. Estoque — Inventário')
para('Menu Fórmulas → Estoque — Inventário. Contagem geral do estoque com apuração de diferenças em lote (além do ajuste avulso que existe em Lotes).')
imagem('inventario', 'Inventário em massa')
passo('Clique em "+ Novo inventário", dê uma descrição e escolha o escopo (só lotes aprovados ou todos com saldo). O saldo atual é congelado como base.')
passo('Na planilha, digite a quantidade real contada de cada lote. A diferença aparece na hora (verde para sobra, vermelho para falta) e é salva automaticamente. Use a busca para achar o item.')
passo('Ao terminar, clique em "Fechar inventário": todos os ajustes são aplicados de uma vez (atualiza os lotes e lança os movimentos no kardex). Lotes não contados ficam inalterados.')
nota('Dá para cancelar a sessão sem aplicar nada. Ideal para o inventário inicial no corte e para as contagens periódicas.')

# ══════════ 17. HISTÓRICO DE PREÇOS ══════════
doc.add_page_break(); h1('16. Histórico de Preços do Ativo')
para('Em Ativos, abra um ativo e clique em "📈 Histórico de preços". Serve para acompanhar a evolução do custo de um insumo ao longo do tempo.')
passo('Cada entrada de nota fiscal e cada alteração manual de preço registra um ponto na linha do tempo.')
passo('A tabela mostra, por data e origem, os valores de compra, custo e venda.')
nota('Use para negociar com fornecedores e revisar a margem de venda quando o custo de um insumo sobe.')

# ══════════ 18. PRODUÇÃO INTERNA (diluições e bases) ══════════
doc.add_page_break(); h1('17. Produção Interna')
para('Menu Fórmulas → Produção Interna. Onde a farmácia prepara suas diluições e bases (ex.: Testosterona 1:10), gerando lote próprio com rastreabilidade — em vez de comprar o diluído pronto.')
imagem('producao_interna', 'Produção interna de diluições')
passo('Clique em "+ Nova produção"; escolha o diluído/base a produzir e a quantidade. Se o produto já tem receita vinculada, ela aparece; senão, busque a fórmula.')
passo('O sistema escala a receita e calcula os insumos proporcionais (o ativo puro + o veículo/excipiente).')
passo('Na pesagem, escolha o lote FEFO de cada insumo (só aprovados) e informe o pesado — igual à produção de OM.')
passo('Clique em "Concluir": os insumos são baixados do estoque e é gerado um lote novo do diluído (nº PI-AAAAMM-NNN), já aprovado e disponível para as fórmulas.')
nota('O lote produzido guarda o teor, o fator de diluição e o vínculo ao lote da matéria-prima pura consumida (rastreabilidade completa).')

# ══════════ 19. CONTROLADOS / SNGPC ══════════
doc.add_page_break(); h1('18. Controlados / SNGPC')
para('Menu Fórmulas → Controlados / SNGPC. Escrituração das substâncias sujeitas a controle especial (Portaria 344/98) e geração do arquivo para a ANVISA.')
imagem('sngpc', 'Controlados / SNGPC')
h3('Marcar as substâncias controladas')
passo('Em Ativos → Editar, marque "Substância controlada" e informe a classe (A1, A2, A3, B1, B2, C1, AM...). Só assim o sistema passa a tratar aquele ativo como controlado.')
h3('Escriturar e fechar o balanço')
passo('A tela mostra o livro de movimentos. Use "Lançar" para registrar manualmente uma entrada, saída, perda, transferência ou inventário de uma substância.')
passo('Quando você conclui uma OM que tem componente controlado, a SAÍDA é escriturada automaticamente — com prescritor, comprador e nº da receita — sem digitação e sem duplicar.')
passo('"Balanço" mostra o saldo por substância no período (o BSPO). "Gerar XML" produz o arquivo para transmissão à ANVISA.')
nota('A transmissão ao webservice da ANVISA ainda é manual: o sistema gera o XML para você enviar pelo portal. Antimicrobianos (AM) só precisam ser escriturados se forem manipulados (a Magis não manipula).')

# ══════════ 20. LGPD E CID-10 ══════════
doc.add_page_break(); h1('19. LGPD e CID-10')
para('Recursos de conformidade e apoio no cadastro do paciente e no orçamento.')
h3('Consentimento e anonimização (LGPD)')
passo('No cadastro do cliente (Histórico → editar), a seção "Consentimento (LGPD)" registra se o paciente consentiu, a data e o canal (verbal, WhatsApp, formulário, termo).')
passo('O botão "🔒 Anonimizar" (apenas gestor) apaga os dados pessoais do paciente preservando as OMs por rastreabilidade — atende ao direito ao esquecimento.')
nota('Todo acesso aos dados de saúde de um paciente fica registrado numa trilha de auditoria.')
h3('CID-10 no orçamento')
passo('No editor de orçamento, o campo "CID-10" permite associar o diagnóstico por código ou descrição (autocomplete).')
nota('O CID é sempre OPCIONAL — nunca trava o orçamento.')

# ══════════ 21. FORMAS FARMACÊUTICAS (PARÂMETROS) ══════════
doc.add_page_break(); h1('20. Formas Farmacêuticas (parâmetros)')
para('Menu Cadastros → Formas Farmacêuticas. Cada forma (Cápsula, Creme, Floral, Envelope…) tem parâmetros próprios que o sistema usa nos cálculos e na produção — nada fica fixo no código, tudo é cadastrado aqui (mesma filosofia dos Parâmetros do sistema de origem).')
imagem('form_forma', 'Cadastro da forma — Validade padrão e Modo de preparo')
tabela_campos([
 ('Nome / Tipo', True, 'Identificação e tipo da forma (cápsula, creme, envelope, floral, etc.).'),
 ('Volume / Cápsula', False, 'Volume-base e, para cápsulas, tipo e número.'),
 ('Custo fixo / Margem', False, 'Custo fixo da forma (R$ ou %) e margem padrão de venda.'),
 ('Validade padrão (dias)', True, 'Prazo de validade da fórmula manipulada nesta forma (RDC 67). Usado na OM; a validade final será a MENOR entre este prazo e a validade do lote usado.'),
 ('Modo de preparo / precauções', False, 'Procedimento padrão de manipulação e precauções (RDC 67/BPF). É herdado por toda OM desta forma e pode ser ajustado por receita na produção.'),
])
nota('Cadastre a validade e o modo de preparo de cada forma com a Farmacêutica RT antes de operar — são esses parâmetros que alimentam a validade do rótulo e o procedimento impresso na OM.')

# ══════════ 22. ENTREGAS ══════════
doc.add_page_break(); h1('21. Entregas')
para('Módulo de última milha, integrado ao card do CRM e ao funil Pós-vendas. A entrega é criada automaticamente quando o pedido é aprovado (card ganho).')
imagem('entregas', 'Painel de entregas')
h3('A entrega no card')
passo('Ao fechar o card como GANHO (ele vai para o funil Pós-vendas), a Entrega é criada sozinha (status pendente), já vinculada ao cliente.')
passo('Na aba "🚚 Entrega" do card: escolha o tipo (Cliente = Uber/99/transporte do próprio cliente, Correio, Motoboy ou Balcão/retirada), informe o endereço (digite o CEP para preencher automático, ou escolha um endereço já salvo do cliente) e a forma de pagamento.')
passo('Acompanhe pelo funil Pós-vendas: Pronto para Entrega → Em Rota → Entregue / Não Entregue → NPS.')
h3('Painel gerencial')
passo('O menu Entregas abre um painel com os indicadores: entregas por status, custo, valores a receber e recebidos, por tipo e por dia.')
nota('Duas travas de operação: (1) o card não avança para "Pronto para Entrega" sem endereço (exceto Balcão/retirada); (2) o card não avança para "NPS" sem o pagamento registrado.')

# ══════════ 23. CAIXA ══════════
doc.add_page_break(); h1('22. Caixa (financeiro)')
para('Menu Financeiro → Caixa. Recebe as vendas dos cards ganhos e faz a baixa dos pagamentos, com taxas de cartão, sessão de caixa e conciliação.')
imagem('caixa_vendas', 'Caixa — Vendas e recebimento')
h3('Receber uma venda')
passo('Cada card ganho gera uma venda "a receber". Em Caixa → Vendas, clique em "Receber": escolha a(s) forma(s) de pagamento (aceita split e parcelas) e confirme — a venda é quitada, com o recibo e a taxa da operadora já calculada.')
passo('Pagamento na entrega: ao marcar a Entrega como paga (com a forma) na aba do card, a baixa é feita no Caixa automaticamente — e o contrário também: dar baixa no Caixa marca a entrega como paga (libera o NPS).')
passo('Estorno: em Vendas, o botão "Estornar" (com motivo) reverte o recibo de forma auditada (guarda motivo, quem e quando) e reabre a venda.')
h3('Sessão e conciliação')
passo('Sessão (Caixa → Sessão): abra o caixa com o saldo inicial e feche no fim do dia — o sistema confere o esperado (dinheiro recebido) com o contado e aponta a diferença.')
passo('Conciliação (Caixa → Conciliação): confirme o que caiu de cartão/PIX na data prevista e antecipe recebíveis quando precisar (aplica a taxa de antecipação).')
nota('As formas de pagamento e as taxas (MDR) por operadora e faixa de parcelas são configuradas em Configuração → TAO Caixa.')

# ══════════ 23. VALOR DO ESTOQUE ══════════
doc.add_page_break(); h1('23. Estoque — Valor do Estoque')
para('Menu Fórmulas → Estoque — Valor do Estoque. A foto financeira do estoque: quanto vale o saldo de cada produto, a custo, a preço de compra e a preço de venda.')
imagem('valor_estoque', 'Valor do Estoque — valorização por produto (editável)')
para('Cada linha traz o saldo do produto (lotes com estoque) multiplicado pelos seus valores unitários. Cada total bate com o seu unitário: Custo total = custo unit × qtde; Compra total = compra unit × qtde; Venda total = venda unit × qtde.')
tabela_campos([
 ('Filtro Grupo', False, 'Matéria-prima, embalagem ou todos.'),
 ('Filtro Status do lote', False, 'Todos (estoque físico) · Só liberados · Em quarentena.'),
 ('Custo unit / total', False, 'Custo de mercado do cadastro. Em cinza quando o custo não foi cadastrado (custo total = 0).'),
 ('Compra unit / total', False, 'Último preço de compra pago — é a base do "Valor do estoque".'),
 ('Venda unit / total', False, 'Preço de venda do cadastro.'),
])
passo('Os cards do topo somam: Valor a custo, Valor do estoque (a compra), Valor a venda e a Margem potencial.')
passo('Os valores unitários são EDITÁVEIS direto na tabela — digite e saia do campo; grava no cadastro do ativo e os totais recalculam na hora (verde = salvo).')
nota('A tela também "denuncia" preços errados do cadastro (ex.: um item com venda igual ao custo, ou um valor absurdo por grama que deveria ser por litro/frasco) — corrija ali mesmo.')

# ══════════ 24. CERTIFICADOS / LAUDOS ══════════
doc.add_page_break(); h1('24. Estoque — Certificados / Laudos')
para('Menu Fórmulas → Estoque — Certificados / Laudos. Consulta os laudos importados por lote e EMITE o Certificado de Análise da farmácia (RDC 67) a partir do laudo do fornecedor.')
imagem('certificados', 'Certificados / Laudos — consulta e emissão')
passo('Busque por ativo, lote ou fabricante; filtre por resultado (Aprovado/Reprovado).')
passo('Clique em "ver / certificado" para abrir os dados extraídos do laudo (produto, lote, validade, fabricante, ensaios) e o PDF do fornecedor.')
passo('Botão "📄 Gerar Certificado": emite o Certificado de Análise da farmácia — documento pronto para imprimir/salvar em PDF, com o cabeçalho da farmácia, os dados do lote, os ensaios, a identificação e a assinatura da responsável técnica.')
nota('O PDF do fornecedor fica guardado em nuvem com acesso protegido (URL temporária) — atende à LGPD.')

# ══════════ 25. UNIDADES DE MEDIDA ══════════
doc.add_page_break(); h1('25. Cadastros — Unidades de Medida')
para('Menu Cadastros → Unidades de Medida. O cadastro único das unidades usadas em compra e venda — é o que padroniza o sistema e alimenta a conversão automática na Entrada de NF.')
imagem('unidades', 'Unidades de Medida — cadastro e fatores')
tabela_campos([
 ('Sigla', True, 'Ex.: KG, G, MG, L, ML, UN, CAP.'),
 ('Dimensão', True, 'Massa, volume ou contagem — a conversão só acontece dentro da mesma dimensão.'),
 ('Fator para a base', True, 'Quanto vale na unidade base da dimensão (massa: G=1, KG=1000; volume: ML=1, L=1000).'),
])
passo('Botão "⚙ Criar unidades padrão": semeia de uma vez as unidades comuns que faltarem.')
nota('Essas unidades aparecem como combo no cadastro do ativo (compra/venda) e no módulo de Cotações — fim do texto livre, que causava erro de conversão.')

# ══════════ GLOSSÁRIO ══════════
doc.add_page_break(); h1('Glossário — termos usados no sistema')
para('Se você é novo na farmácia de manipulação, consulte aqui os termos que aparecem nas telas e neste manual.')
tabela_campos([
 ('OM — Ordem de Manipulação', False, 'Documento que autoriza e orienta a produção de uma fórmula. Nasce do orçamento aprovado.'),
 ('Ficha de Pesagem', False, 'Impresso da OM que diz qual produto e quanto pesar de cada componente, com o lote usado.'),
 ('Ativo / Matéria-prima (MP)', False, 'Insumo da fórmula: princípio ativo, excipiente ou base.'),
 ('QSP', False, 'Latim "quantidade suficiente para". É o excipiente que completa o volume da cápsula/forma.'),
 ('Dose', False, 'Quantidade do princípio ativo por unidade da fórmula (ex.: 50 mg por cápsula).'),
 ('Teor (%)', False, 'Concentração real do princípio ativo no lote. Quanto menor o teor, mais se pesa para compensar.'),
 ('Equivalência sal↔base', False, 'Fator que ajusta a quantidade quando o prescrito e o insumo são formas diferentes da mesma substância (ex.: o sal vs. a base).'),
 ('Diluição (1:N)', False, 'Insumo que já vem diluído de fábrica (ex.: 1:100). Multiplica a quantidade a pesar.'),
 ('VOLAPA', False, 'Volume aparente do pó — usado para calcular quanto o excipiente (QSP) completa dentro da cápsula.'),
 ('FEFO', False, 'First Expire, First Out: usa primeiro o lote de validade mais próxima.'),
 ('Lote', False, 'Identificação de um recebimento de matéria-prima, com validade e quantidade próprias. Base da rastreabilidade.'),
 ('CQ — Controle de Qualidade', False, 'Aprovação/reprovação do lote no recebimento. Só lote aprovado entra na produção (RDC 67).'),
 ('Kardex', False, 'Extrato de todas as entradas e saídas de um produto no estoque.'),
 ('Curva ABC', False, 'Classificação de importância do item no estoque (A = mais crítico).'),
 ('Card (CRM)', False, 'O atendimento do cliente no funil de vendas/pós-vendas — onde o pedido começa e é acompanhado até a entrega.'),
 ('RDC 67/2007', False, 'Norma da ANVISA para farmácias de manipulação: boas práticas, rótulo e rastreabilidade.'),
 ('Portaria 344/98', False, 'Regras para substâncias e medicamentos sob controle especial.'),
 ('SNGPC', False, 'Sistema Nacional de Gerenciamento de Produtos Controlados: escrituração enviada à ANVISA.'),
 ('DCB', False, 'Denominação Comum Brasileira — o nome oficial do princípio ativo.'),
 ('RT — Responsável Técnico', False, 'Farmacêutico responsável pela farmácia; seus dados saem no rótulo.'),
])

doc.save(OUT)
print('DOCX gerado:', OUT, '-', os.path.getsize(OUT)//1024, 'KB')
