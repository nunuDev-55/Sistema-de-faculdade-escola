<?php

return [
    /*
     * O produto: o mesmo código serve qualquer instituição de ensino, cada uma na sua instalação
     * (o seu servidor e a sua base de dados). O nome aparece no sistema interno (menu, entrada, documentos);
     * os portais e o site mostram só a instituição.
     */
    'produto' => [
        'nome' => 'nsEDU',
        'autor' => 'Nuryan Sultane',
    ],

    /*
     * Valores iniciais de uma instituição nova, até serem gravados em Administração → Configuração.
     * Depois valem os da base de dados (tabela definicoes, ver App\Support\Definicoes).
     * Também se podem dar no .env da instalação (INSTITUICAO_NOME=...), para a primeira entrada já sair certa.
     */
    'instituicao' => [
        'nome' => env('INSTITUICAO_NOME', 'A minha instituição'),
        'sigla' => env('INSTITUICAO_SIGLA'),
        // 'superior' (universidade, instituto: cursos, semestres, créditos) ou 'geral' (escola: classes, trimestres)
        'tipo' => env('INSTITUICAO_TIPO', 'superior'),
        'nuit' => env('INSTITUICAO_NUIT'),
        'morada' => env('INSTITUICAO_MORADA'),
        'cidade' => env('INSTITUICAO_CIDADE'),
        'provincia' => env('INSTITUICAO_PROVINCIA', 'MZ-MPM'),
        'telefone' => env('INSTITUICAO_TELEFONE'),
        'email' => env('INSTITUICAO_EMAIL'),
    ],

    /*
     * Regras pedagógicas de origem (mudam-se em Administração → Configuração → Avaliação para seguir o regulamento).
     * A escala e os limites estão aqui só como valores iniciais; ver docs/02-requisitos.md (RN-01 a RN-07).
     */
    'avaliacao' => [
        'escala_maxima' => (int) env('NSEDU_ESCALA_MAXIMA', 20),
        'nota_minima_aprovacao' => (float) env('NSEDU_NOTA_APROVACAO', 10),
        'minimo_admissao_exame' => (float) env('NSEDU_MINIMO_EXAME', 10),
        'minimo_dispensa_exame' => (float) env('NSEDU_MINIMO_DISPENSA', 14),
        'assiduidade_minima' => (int) env('NSEDU_ASSIDUIDADE_MINIMA', 75),
        'casas_decimais' => (int) env('NSEDU_CASAS_DECIMAIS', 2),
    ],

    /*
     * Cópias de segurança (a base de dados e os ficheiros num .zip). Igual ao nsERP.
     */
    'copias' => [
        'automaticas' => (bool) env('NSEDU_COPIAS_AUTOMATICAS', true),
        'pasta_extra' => env('NSEDU_COPIAS_PASTA'),
        'senha' => env('NSEDU_COPIAS_SENHA'),
    ],
];
