{{-- As cores da instituição (Configuração), já validadas em Definicoes::cores(): o CSS tira delas os botões, o menu e as faixas --}}
@php($cores = app(\App\Support\Definicoes::class)->cores())
<style>:root { --cor-empresa: {{ $cores['cor'] }}; --sobre-cor-empresa: {{ $cores['sobre_cor'] }}; --cor-destaque: {{ $cores['destaque'] }}; --sobre-cor-destaque: {{ $cores['sobre_destaque'] }}; }</style>
<meta name="theme-color" content="{{ $cores['cor'] }}">
