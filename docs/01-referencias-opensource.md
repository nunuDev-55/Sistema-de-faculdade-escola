# Referências open source

Estudo de sistemas de gestão escolar e universitária de código aberto no GitHub, feito a 09/11/2026. Tal como o nsERP fez com o Dolibarr, estes sistemas servem só como **referência funcional**: que módulos têm, como se chamam os ecrãs, que regras aplicam. **Nenhum código é copiado** (a maioria é GPL/AGPL, incompatível com software proprietário).

## 1. Sistemas encontrados

| Sistema | Repositório | ★ | Tecnologia | Licença | Último envio | Tipo |
|---|---|---|---|---|---|---|
| Moodle | [moodle/moodle](https://github.com/moodle/moodle) | 7 593 | PHP | GPL-3.0 | 10/2026 | LMS (ensino à distância) |
| Canvas LMS | [instructure/canvas-lms](https://github.com/instructure/canvas-lms) | 6 906 | Ruby on Rails | AGPL-3.0 | 04/2026 | LMS |
| Frappe LMS | [frappe/lms](https://github.com/frappe/lms) | 3 392 | Python + Vue | AGPL-3.0 | 11/2026 | LMS |
| LAVSMS | [4jean/lav_sms](https://github.com/4jean/lav_sms) | 1 176 | **Laravel** | MIT | 03/2024 | Gestão escolar |
| Another School Management System | [hrshadhin/school-management-system](https://github.com/hrshadhin/school-management-system) | 1 154 | **Laravel** (Blade) | AGPL-3.0 | 04/2025 | Gestão escolar |
| OpenEduCat | [openeducat/openeducat_erp](https://github.com/openeducat/openeducat_erp) | 907 | Python (Odoo) | LGPL-3.0* | 11/2026 | ERP educativo |
| Frappe Education | [frappe/education](https://github.com/frappe/education) | 703 | Python (ERPNext) | GPL-3.0* | 10/2026 | ERP educativo |
| RosarioSIS | [francoisjacquet/rosariosis](https://github.com/francoisjacquet/rosariosis) | 652 | PHP | GPL-2.0 | 11/2026 | SIS (informação do estudante) |
| Gibbon | [GibbonEdu/core](https://github.com/GibbonEdu/core) | 638 | PHP | GPL-3.0 | 11/2026 | Gestão escolar |
| Django School Management | [TareqMonwer/Django-School-Management](https://github.com/TareqMonwer/Django-School-Management) | 597 | Django | não indicada | 10/2026 | Gestão escolar |
| openSIS Classic | [OS4ED/openSIS-Classic](https://github.com/OS4ED/openSIS-Classic) | 349 | PHP | GPL* | 10/2026 | SIS (K-12 e superior) |
| OSUMS | [hrshadhin/osums](https://github.com/hrshadhin/osums) | 84 | **Laravel** | AGPL-3.0 | 08/2019 | Gestão universitária |
| OpenEMIS Core | [OpenEMIS/core](https://github.com/OpenEMIS/core) | 24 | PHP (CakePHP) | GPL-2.0 | 11/2026 | Estatística da educação (escola → nacional) |

\* O GitHub não identifica a licença automaticamente; é a indicada no próprio projecto. Estrelas e datas tiradas do GitHub a 09/11/2026.

## 2. Módulos em cada sistema

| Módulo | Frappe Education | OpenEduCat | openSIS | RosarioSIS | Gibbon | LAVSMS / hrshadhin |
|---|---|---|---|---|---|---|
| Candidaturas e admissão | ✓ Student Applicant | ✓ Admission | | | ✓ Application Form | ✓ |
| Ficha do estudante | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Encarregados de educação | ✓ Guardian | ✓ Parent | ✓ | ✓ | ✓ Family | ✓ |
| Docentes e pessoal | ✓ Instructor | ✓ Faculty | ✓ | ✓ | ✓ Staff | ✓ |
| Cursos, disciplinas, planos | ✓ Program / Course | ✓ Course / Subject | ✓ Course Manager | ✓ | ✓ | ✓ |
| Anos e períodos lectivos | ✓ Academic Year / Term | ✓ Batch | ✓ Marking Periods | ✓ | ✓ | ✓ |
| Matrícula e inscrição | ✓ Program / Course Enrollment | ✓ | ✓ Scheduling | ✓ | ✓ | ✓ |
| Turmas | ✓ Student Group | ✓ Batch | ✓ | ✓ | ✓ Form Groups | ✓ |
| Horários e salas | ✓ Course Schedule / Room | ✓ Timetable | ✓ | ✓ | ✓ Timetable | ✓ |
| Assiduidade | ✓ | ✓ Attendance Sheet | ✓ | ✓ | ✓ | ✓ |
| Avaliação e notas | ✓ Assessment Plan / Result / Grading Scale | ✓ Exam / Marksheet | ✓ Gradebook | ✓ Gradebook | ✓ Markbook | ✓ |
| Pautas, boletins, certificados | ✓ Report Card | ✓ | ✓ Report Cards / Transcripts | ✓ | ✓ Reports | ✓ |
| Propinas e pagamentos | ✓ Fee Structure / Fee Schedule / Fees | ✓ Fees | | ✓ Student Billing | ✓ Finance | ✓ |
| Portal do estudante / encarregado | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Comunicação (avisos, mensagens) | | | ✓ | | ✓ Messenger | ✓ |
| Biblioteca | | ✓ | | | ✓ | ✓ |
| Disciplina / comportamento | | | | ✓ | ✓ Behaviour | |

## 3. O que levamos de cada um

| Sistema | O que serve de referência |
|---|---|
| **Frappe Education** | O modelo de dados mais limpo: separa **Programa** (curso) de **Disciplina**, **Matrícula no programa** (anual) de **Inscrição na disciplina**, e o **Plano de avaliação** (componentes e pesos) do **Resultado**. A **escala de classificação** é configurável. A **Estrutura de propinas** gera as cobranças por período. |
| **OpenEduCat** | O ciclo de vida da **admissão** (candidatura → avaliada → admitida → matriculada) e a **folha de presenças** por aula. |
| **openSIS / RosarioSIS** | O **caderno do docente** (gradebook) com pesos por componente, os **períodos de avaliação** configuráveis, os **boletins** e o **histórico escolar** (transcript). Pesquisa de estudantes por turma, faltas, notas e média. |
| **Gibbon** | O **portal da família** (um encarregado vê vários educandos), o **planeador de aulas** e a **comunicação** com a escola. |
| **OpenEMIS** | Os **dados estatísticos** que uma escola reporta ao Ministério (alunos por classe, sexo, idade, aproveitamento). |
| **LAVSMS / hrshadhin** | Exemplos do mesmo framework (Laravel): perfis da escola (Super Admin, Admin, Docente, Contabilista, Encarregado, Aluno), promoção de alunos de classe, pautas por exame. |
| **Moodle / Canvas** | **Fora do âmbito**: são plataformas de ensino (conteúdos, trabalhos, testes online). O nosso sistema é de **gestão**; a ligação a um LMS fica para uma fase futura (exportar turmas e importar notas). |

## 4. Conclusões para o nosso sistema

1. **Sistema de gestão académica e financeira**, não um LMS. As aulas online ficam no Moodle (ou outro), se a instituição quiser.
2. **Um modelo, dois tipos de instituição**: ensino superior (cursos, semestres, créditos, inscrição por disciplina, exames e recorrência) e ensino geral (classes, trimestres, todas as disciplinas da classe, passagem de classe). A diferença fica em `Definicoes`, como o aluguer no nsERP.
3. **Separação clara** que todos os sistemas maduros fazem: estrutura curricular (o que se ensina) ≠ oferta lectiva (o que abre neste período, com que docente e turma) ≠ percurso do estudante (matrícula, inscrições, notas).
4. **Pauta como documento**: nasce em rascunho, o docente lança, valida-se, publica-se e fecha. Depois de fechada não se altera — uma correcção é um novo registo com motivo (a mesma regra dos documentos do nsERP).
5. **Propinas reaproveitam a facturação do nsERP**: cobrança → pagamento (parcial ou total) → recibo, com numeração sem saltos e dinheiro em bcmath.
6. **Portais separados** para estudante/encarregado e para candidatos, com guarda própria — o mesmo padrão da "Área do cliente" do nsERP.
