# Requisitos

Planta de requisitos do sistema de gestão de universidades e escolas (nome provisório **nsEDU**). Os códigos (RF, RNF, RN) servem para ligar cada requisito aos casos de uso, aos testes e ao plano.

Prioridade (MoSCoW): **D** = deve ter (1.ª versão) · **E** = é importante · **P** = pode ter · **F** = fica para depois.

## 1. Visão

Uma aplicação web para uma instituição de ensino gerir, num só sítio, **o percurso académico** (candidatura → matrícula → inscrições → aulas e presenças → avaliação → pauta → certificado) e **as finanças do estudante** (propinas, emolumentos, pagamentos, recibos), com portais para estudantes, encarregados e candidatos.

- **Uma instituição por instalação**, como o nsERP: cada instituição tem o seu servidor, base de dados e endereço; nome, logótipo, cores e textos mudam-se em Administração.
- **Dois tipos de instituição**, escolhidos em Configuração:

| | Ensino superior (universidade, instituto) | Ensino geral (escola primária e secundária) |
|---|---|---|
| Estrutura | Faculdade / Escola → Departamento → Curso → Plano curricular | Escola → Nível / Ciclo → Classe |
| Período | Semestre | Trimestre |
| Unidade de ensino | Disciplina (unidade curricular) com créditos | Disciplina da classe |
| Inscrição | O estudante escolhe as disciplinas, com precedências | Todas as disciplinas da classe, automaticamente |
| Avaliação | Frequência → exame normal → recorrência | Avaliação contínua por trimestre → média anual (→ exame, nas classes que o tenham) |
| Fim do ano | Créditos acumulados, transição de ano | Passa / não passa de classe |
| Quem acompanha | O próprio estudante | O encarregado de educação |

## 2. Actores

| Actor | Quem é | Onde entra |
|---|---|---|
| Visitante | Qualquer pessoa | Site público |
| Candidato | Quem se candidata a um curso / vaga | Portal do candidato |
| Estudante | Matriculado na instituição | Portal do estudante |
| Encarregado de educação | Pai, mãe ou responsável (um ou vários educandos) | Portal do estudante (vista de encarregado) |
| Docente | Professor | Sistema interno (perfil Docente) |
| Director de curso / de turma | Coordena um curso ou uma turma | Sistema interno |
| Secretaria académica | Registo académico: matrículas, inscrições, documentos | Sistema interno |
| Tesouraria (Financeiro) | Propinas, pagamentos, dívidas | Sistema interno |
| Direcção (Reitoria / Director pedagógico) | Aprova, consulta relatórios | Sistema interno |
| Administrador | Configura o sistema, utilizadores e permissões | Sistema interno |
| Sistemas externos | Pagamentos móveis (M-Pesa, e-Mola), banco, e-mail, SMS/WhatsApp, LMS | Integrações |

## 3. Requisitos funcionais

### 3.1 Administração e configuração (herdado do nsERP)

| Código | Requisito | Prio. |
|---|---|---|
| RF-01 | Configurar a instituição: nome, sigla, NUIT, alvará / decreto de criação, morada, contactos, logótipo, cores, tipo de instituição. | D |
| RF-02 | Gerir utilizadores internos, perfis e a matriz de permissões; activar e desactivar contas. | D |
| RF-03 | Entrada com senha e verificação em dois passos (código do telemóvel) para o pessoal. Os estudantes entram com o **número de estudante**. A senha tem **validade** configurável e o sistema obriga a mudá-la quando caduca (como no eDondzo). | D |
| RF-04 | Registar em auditoria quem fez o quê e quando, com os valores antes e depois. | D |
| RF-05 | Cópias de segurança da base de dados, criadas e descarregadas em Administração. | D |
| RF-06 | Definir a numeração dos documentos (n.º de estudante, recibos, declarações, certificados) por série e ano. | D |
| RF-07 | Listas editáveis: tipos de documento de identificação, províncias, motivos de falta, tipos de emolumento, métodos de pagamento. | E |

### 3.2 Estrutura académica

| Código | Requisito | Prio. |
|---|---|---|
| RF-10 | Gerir unidades orgânicas em árvore (faculdade / escola / departamento). | D |
| RF-11 | Gerir cursos (grau, duração, regime, turno, unidade orgânica, director) ou, no ensino geral, níveis e classes. | D |
| RF-12 | Gerir disciplinas: código, nome, créditos, carga horária (teórica, prática), unidade orgânica. | D |
| RF-13 | Gerir planos curriculares com versões: que disciplinas em que ano e semestre, obrigatórias ou opcionais. Um plano em uso não se altera; cria-se uma versão nova. | D |
| RF-14 | Definir precedências entre disciplinas (só se inscreve em B depois de aprovar A). | E |
| RF-15 | Gerir anos lectivos e períodos (semestres / trimestres) com datas de inscrição, aulas, avaliações e exames. | D |
| RF-16 | Gerir salas (capacidade, tipo: sala, laboratório, anfiteatro). | E |
| RF-17 | Gerir **delegações e campus**. Cada matrícula indica o curso, o **regime** (turno: manhã, tarde, noite), a delegação e o campus (como no eDondzo). | D |

### 3.3 Candidaturas e admissão

| Código | Requisito | Prio. |
|---|---|---|
| RF-20 | Abrir uma fase de candidaturas com vagas por curso, datas e taxa de candidatura. | E |
| RF-21 | O candidato preenche a candidatura online, escolhe até N cursos por ordem de preferência e envia os documentos (BI, certificado). | E |
| RF-22 | A secretaria verifica a candidatura, regista a nota do exame de admissão e publica a lista de admitidos por ordem de classificação até esgotar as vagas. | E |
| RF-23 | O candidato admitido passa a estudante com um clique (os dados não se voltam a escrever). | E |

### 3.4 Estudantes e encarregados

| Código | Requisito | Prio. |
|---|---|---|
| RF-30 | Ficha do estudante: dados pessoais, documento de identificação, NUIT (opcional), contactos, morada (**Província → Distrito → Localidade**, em cascata), foto, necessidades especiais, número de estudante gerado pelo sistema. | D |
| RF-31 | Separadores da ficha: Dados · Matrículas · Inscrições · Notas · Presenças · Finanças · Documentos · Notas internas. | D |
| RF-32 | Associar um ou mais encarregados a um estudante (parentesco, contacto principal); um encarregado pode ter vários educandos. | D |
| RF-33 | Importar estudantes de um ficheiro Excel com modelo, pré-visualização e confirmação (como no nsERP). | E |
| RF-34 | Pesquisar e filtrar estudantes por curso, classe, turma, ano, estado, dívida, aproveitamento. | D |

### 3.5 Matrícula e inscrição

| Código | Requisito | Prio. |
|---|---|---|
| RF-40 | Matricular (ou renovar a matrícula de) um estudante num curso / classe para um ano lectivo, com o plano curricular em vigor. | D |
| RF-41 | Ensino superior: inscrever o estudante nas disciplinas do período, respeitando precedências, limite de créditos e vagas da turma. | D |
| RF-42 | Ensino geral: inscrever automaticamente em todas as disciplinas da classe. | D |
| RF-43 | Distribuir os estudantes por turmas (manual ou automático por ordem alfabética / capacidade). | D |
| RF-44 | Pedido de inscrição online pelo estudante, aprovado pela secretaria ou pelo director de curso. | E |
| RF-45 | Mudar o estado da matrícula: activa, suspensa, anulada, transferida, concluída. | D |
| RF-46 | Equivalências e creditação de disciplinas feitas noutra instituição. | P |
| RF-47 | **Inscrição semestral**: a cada semestre o estudante renova a inscrição, que tem código, estado (pré-inscrição → confirmada), **taxa de inscrição** e as **disciplinas em atraso** que pode repetir (como no eDondzo). | D |
| RF-48 | **Comprovativo de inscrição** em PDF, com reimpressão (**2.ª via**) a qualquer momento. | D |

### 3.6 Docentes e distribuição de serviço

| Código | Requisito | Prio. |
|---|---|---|
| RF-50 | Ficha do docente: grau académico, categoria, regime (tempo inteiro / parcial), unidade orgânica, ligada a um utilizador. | D |
| RF-51 | Distribuição de serviço: que docente lecciona que disciplina em que turma e período. | D |
| RF-52 | Horário por turma, por docente e por sala, sem sobreposições (o sistema avisa). | E |

### 3.7 Aulas e assiduidade

| Código | Requisito | Prio. |
|---|---|---|
| RF-60 | O docente regista o sumário e as presenças de cada aula (presente, falta, atraso, falta justificada). | D |
| RF-61 | Justificar faltas com motivo e documento. | E |
| RF-62 | Percentagem de assiduidade por estudante e disciplina; aviso quando se aproxima do limite. | E |
| RF-63 | O docente anexa **materiais** a cada aula (ficheiros, ligações); o estudante vê a lista das aulas e descarrega-os no portal. | E |

### 3.8 Avaliação e pautas

| Código | Requisito | Prio. |
|---|---|---|
| RF-70 | Definir a **escala de classificação por intervalos** (0–20 por defeito), com o grau de cada intervalo (Reprovado, Suficiente, Bom...), as casas decimais e o arredondamento. Os intervalos têm de começar em 0, ser contíguos e chegar ao máximo (padrão do Frappe Education, ver [08](08-estudo-sistemas-academicos.md)). | D |
| RF-71 | Definir o plano de avaliação de cada disciplina na turma: componentes (testes, trabalhos, exame), pesos e datas. A soma dos pesos tem de dar 100 %. Modelo de origem: **2 avaliações + 2 trabalhos** (o do eDondzo). | D |
| RF-72 | O docente lança as notas de cada componente num ecrã tipo folha (teclado, colar do Excel). | D |
| RF-73 | O sistema calcula a média de frequência e a situação (excluído, admitido a exame, dispensado) segundo as regras da instituição. | D |
| RF-74 | Pautas de frequência e de exame (épocas **Normal, Recorrência e Especial**): rascunho → submetida pelo docente → validada pelo director de curso → publicada → fechada. | D |
| RF-75 | Revisão de nota (pedido do estudante, decisão, nova nota com motivo) sem apagar a anterior. | E |
| RF-76 | Ensino geral: médias por trimestre, média anual e decisão de passagem de classe (conselho de notas). | D |
| RF-77 | Créditos acumulados e média do curso (ponderada pelos créditos). | D |
| RF-78 | Importar notas de um LMS (Moodle) por ficheiro. | F |

### 3.9 Finanças do estudante

| Código | Requisito | Prio. |
|---|---|---|
| RF-80 | Tabela de preços por ano lectivo: matrícula, **taxa de inscrição semestral**, propina (mensal, com vencimento num **dia fixo**, por defeito o dia 10), emolumentos (declaração, certificado, exame de recorrência, 2.ª via de cartão). O preço pode depender da **categoria do estudante** (regular, bolseiro, funcionário, estrangeiro, transferido), como no Frappe Education. | D |
| RF-81 | Gerar as cobranças do ano para cada estudante matriculado, com vencimentos. | D |
| RF-82 | Multa automática por atraso (percentagem ou valor fixo, configurável). | E |
| RF-83 | Bolsas e descontos (percentagem ou valor, por estudante, com aprovação). | E |
| RF-84 | Registar pagamentos parciais ou totais (numerário, banco, M-Pesa, e-Mola, POS) e emitir recibo numerado em A4 / PDF. | D |
| RF-85 | Conta corrente do estudante: **dívida actual**, **saldo dos adiantamentos** (pagamentos antecipados) e **extracto** por ano e semestre (cobrança, vencimento, valor, data do pagamento, valor pago, dívida). | D |
| RF-86 | Bloqueios configuráveis por dívida: inscrição, ver notas, pedir documentos. | E |
| RF-87 | Lista de devedores e lembretes por e-mail / SMS / WhatsApp. | E |
| RF-88 | **Referência bancária por cobrança**: cada cobrança recebe uma referência única (entidade da instituição + referência + montante + data limite), que o estudante vê no portal e paga no banco, ATM ou banca móvel. A tesouraria carrega o ficheiro do banco e o sistema dá baixa sozinho (como no eDondzo). | D |
| RF-89 | Pagamento online com confirmação imediata (M-Pesa, e-Mola). | F |

### 3.10 Documentos académicos e requerimentos

| Código | Requisito | Prio. |
|---|---|---|
| RF-90 | Emitir declaração de matrícula, de frequência, certificado de habilitações e histórico escolar a partir dos dados do sistema. | D |
| RF-91 | Cada documento emitido tem número, data, assinatura e um código QR que confirma a autenticidade numa página pública. | E |
| RF-92 | O estudante pede o documento no portal; o pedido gera o emolumento e segue: pedido → pago → em preparação → pronto → entregue. | E |
| RF-93 | Boletim de notas (ensino geral) e pauta impressa por turma e disciplina. | D |
| RF-94 | **Inscrição na graduação**: os finalistas inscrevem-se na cerimónia; a secretaria confere, reimprime e tira a lista. | P |

### 3.11 Portais

| Código | Requisito | Prio. |
|---|---|---|
| RF-100 | Portal do estudante, com os mesmos passos do eDondzo: **Inscrição · Notas de Frequência · Exames · Situação Financeira (extracto e referências) · Plano Curricular · Dados Pessoais**, mais horário, presenças, materiais das aulas, recibos, pedidos de documentos e avisos. Filtros por Ano Lectivo e Semestre. | D |
| RF-101 | Portal do encarregado: o mesmo para cada educando, à escolha. | D |
| RF-102 | Portal do candidato: candidatura, documentos, estado e resultado. | E |
| RF-103 | Site público com os cursos, notícias e o formulário de candidatura (como o site do nsERP). | P |

### 3.12 Comunicação, painel e relatórios

| Código | Requisito | Prio. |
|---|---|---|
| RF-110 | Avisos para um público (todos, um curso, uma turma, os docentes, os devedores), mostrados no portal e enviados por e-mail. | E |
| RF-115 | **SMS** a um destinatário, a todos, por perfil, por curso ou aos candidatos admitidos. | E |
| RF-111 | Painel inicial por perfil: matrículas do ano, inscrições pendentes, pautas por publicar, propinas recebidas e vencidas. | D |
| RF-112 | Relatórios: estudantes por curso / classe / sexo / idade, aproveitamento por disciplina e turma, taxa de aprovação, assiduidade, receitas e dívidas. | E |
| RF-113 | Exportar qualquer lista para Excel. | D |
| RF-114 | Mapas estatísticos para o Ministério (efectivos escolares, aproveitamento). | P |

## 4. Requisitos não funcionais

| Código | Categoria | Requisito |
|---|---|---|
| RNF-01 | Tecnologia | O mesmo stack do nsERP: Laravel 12 (PHP 8.2+), Inertia 2, React 19 + TypeScript, Tailwind CSS 4, shadcn/ui, MariaDB 10.4+ (ver [Arquitectura](05-arquitectura.md)). |
| RNF-02 | Idioma | Interface, mensagens e documentos em português (Moçambique): datas DD/MM/AAAA, dinheiro em MZN com espaço nos milhares e vírgula decimal. |
| RNF-03 | Telemóvel | Os portais funcionam bem num telemóvel com ecrã pequeno e rede lenta: páginas leves, tabelas que viram cartões, sem descarregar ficheiros grandes. |
| RNF-04 | Segurança | Senhas com bcrypt, dois passos para o pessoal, permissões verificadas sempre no servidor, cabeçalhos de segurança, protecção CSRF, limite de tentativas de entrada. |
| RNF-05 | Privacidade | Dados de menores e notas visíveis só a quem tem direito: o estudante vê as suas, o encarregado as dos educandos, o docente as das suas turmas. As notas internas nunca saem para os portais. |
| RNF-06 | Integridade | Pautas fechadas, recibos e certificados não se alteram nem se apagam. Recibos e certificados têm assinatura encadeada (como `IntegridadeDocumentos` do nsERP). |
| RNF-07 | Desempenho | Aguentar o pico das inscrições: 500 utilizadores ao mesmo tempo, listas com paginação e resposta abaixo de 2 s num alojamento partilhado. |
| RNF-08 | Disponibilidade | Cópia de segurança diária automática; recuperação testada. |
| RNF-09 | Alojamento | Corre num alojamento partilhado (Hostinger, como o nsERP) ou num servidor local (XAMPP), sem serviços extra obrigatórios. |
| RNF-10 | Qualidade | Testes automáticos (PHPUnit, SQLite em memória), Larastan nível 5, Pint, ESLint, Prettier e TypeScript a verde no GitHub Actions antes de juntar ao ramo principal. |
| RNF-11 | Manutenção | Arquitectura em camadas verificada por testes (como `ArquitecturaTest` do nsERP); documentação de base de dados, permissões e casos de uso gerada a partir do código. |
| RNF-12 | Acessibilidade | Contraste suficiente nos dois temas (claro e escuro), navegação por teclado, etiquetas nos campos. |
| RNF-13 | Auditoria | Todas as alterações de notas, pagamentos e estados ficam registadas com o autor, a data e os valores antes e depois. |

## 5. Regras de negócio

Os valores entre parênteses são os de origem; mudam-se em Configuração para seguir o regulamento pedagógico de cada instituição.

| Código | Regra |
|---|---|
| RN-01 | A escala de classificação é configurável por **intervalos** (0 a 20 de origem), cada um com o seu grau; a aprovação é com 10 ou mais. Os intervalos começam em 0, não se sobrepõem nem deixam falhas e chegam ao máximo (ver `App\Support\Escala`). |
| RN-02 | Ensino superior — média de frequência: abaixo de 10 → **excluído**; de 10 a 13 → **admitido a exame**; 14 ou mais → **dispensado** do exame (limites configuráveis). |
| RN-03 | Nota final = nota do exame, ou combinação da frequência com o exame (pesos configuráveis). Reprovado no exame normal → pode ir a **recorrência** (com emolumento). A época **especial** abre-se para os casos previstos no regulamento (ex.: finalistas, trabalhadores-estudantes). |
| RN-04 | Assiduidade abaixo do mínimo (75 %) → excluído por faltas, seja qual for a nota. |
| RN-05 | Só se inscreve numa disciplina quem aprovou as precedentes e não ultrapassa o limite de créditos do período (configurável). |
| RN-06 | Os créditos seguem o SNATCA (1 crédito ≈ 25–30 horas de trabalho do estudante); confirmar com o regulamento da instituição. |
| RN-07 | Ensino geral: média anual = média das médias dos trimestres (ou ponderada); a passagem de classe segue os critérios definidos (n.º máximo de negativas, disciplinas nucleares). |
| RN-08 | O número de estudante é único, gerado uma vez (ex.: `2026/00123`) e nunca reutilizado. |
| RN-09 | Uma pauta publicada não se altera; corrige-se por **revisão**, que guarda a nota anterior, a nova, o motivo e quem decidiu. |
| RN-10 | Um docente só lança notas e presenças nas turmas da sua distribuição de serviço e só enquanto a pauta está em rascunho. |
| RN-11 | Dinheiro nunca em `float`: contas com bcmath, `decimal(15,2)` na base (regra do nsERP). |
| RN-12 | Numeração sem saltos nem repetidos, atribuída dentro da transacção que grava o documento (regra do nsERP). |
| RN-13 | Um pagamento abate as cobranças mais antigas primeiro, salvo indicação em contrário; um recibo emitido só se anula com outro documento. |
| RN-14 | Com dívida vencida acima do limite configurado, o estudante não se inscreve, não vê as notas ou não pede documentos (cada bloqueio liga-se em separado). |
| RN-15 | Um plano curricular com estudantes matriculados não se altera; cria-se uma versão nova. Cada estudante fica no plano em que entrou. |
| RN-16 | O portal só mostra notas e situações de **pautas publicadas**. Antes disso a disciplina aparece "Por lançar", nunca "Excluído" ou "Reprovado" (problema visto no eDondzo). |
| RN-17 | Por semestre há uma taxa de inscrição e as propinas mensais; um pagamento acima do devido fica como **adiantamento** e abate a próxima cobrança. |

## 6. Fora do âmbito (1.ª versão)

Conteúdos de aula, trabalhos e testes online (LMS) · salários e recursos humanos completos · contabilidade geral · biblioteca · transporte e cantina · alojamento estudantil. Alguns entram no [plano](ROADMAP.md) como fases futuras.
