/* ==========================================================================
   Crew - Landing page "Raio X da Operação" para provedores - CONFIGURAÇÃO
   Tudo o que muda sem mexer no HTML fica aqui. Veja o README.md desta pasta.
   Campos com [PREENCHER] ainda precisam do dado real.
   ========================================================================== */
window.CREW_CONFIG = {
  /* WhatsApp: só números, com 55 + DDD (o número comercial atual da Crew já está preenchido). */
  WHATSAPP_NUMBER: "5519936196249",
  WHATSAPP_MESSAGE: "Olá! Vim pela página do Raio X da Operação e quero saber mais.",

  /* Para onde o formulário envia o lead (POST em JSON).
     Padrão: o script da própria Crew no servidor, que manda o lead por e-mail para contato@becrew.com.br.
     Para usar Make, n8n, Google Apps Script ou o CRM, troque pela URL do webhook. */
  FORM_ENDPOINT: "/assets/php/lead.php", // <!-- PREENCHER: URL do webhook (opcional) -->

  /* Vagas: alimenta todos os selos e textos "Restam X vagas". */
  VAGAS_RESTANTES: 2,
  VAGAS_TOTAL: 6,

  /* Rastreamento: deixe vazio o que não usar. Com GTM_ID preenchido, as outras tags
     devem ser configuradas dentro do GTM (os IDs abaixo deixam de ser carregados direto). */
  GTM_ID: "",                 // ex.: "GTM-XXXXXXX"
  META_PIXEL_ID: "",          // ex.: "123456789012345"
  GA4_ID: "",                 // ex.: "G-XXXXXXXXXX"
  GADS_CONVERSION_ID: "AW-18453522306",     // ex.: "AW-123456789"
  GADS_CONVERSION_LABEL: "OEppCNK1upsdEILPqd9E",  // ex.: "AbC-D_efG-h12_34-567"

  /* Rodapé e política de privacidade. Enquanto vazios, esses trechos não aparecem na página. */
  RAZAO_SOCIAL: "TIP GAMING LTDA",
  CNPJ: "65.253.910/0001-21",
  ENCARREGADO_LGPD: "",       // <!-- PREENCHER: nome do encarregado (DPO); vazio = atendimento pelo e-mail de contato -->
  EMAIL_CONTATO: "contato@becrew.com.br",
  ENDERECO: "R. Miguel Pascoal, 104 - Vila João Jorge, Campinas - SP, 13041-312",
  INSTAGRAM: "agencia_crew",

  /* Logos de clientes (faixa do topo). Com menos de 3 logos, a faixa não aparece.
     Use só logos autorizados. Arquivos em assets/img/clientes/ (WebP ou SVG). */
  LOGOS_CLIENTES: [
    // { nome: "Provedor X", arquivo: "assets/img/clientes/provedor-x.webp" }
  ],

  /* Cases (seção "Quem trabalha com a Crew fala por nós"). Sem cases, a seção mostra só
     o bloco "Veja um diagnóstico de verdade". Use só dados reais e autorizados.
     video: link do YouTube ou de um arquivo .mp4 (carrega só no clique). */
  CASES: [
    // { provedor: "", cidade: "", logo: "assets/img/cases/x-logo.webp", desafio: "",
    //   resultado: "", prazo: "", depoimento: "", autor: "", cargo: "",
    //   foto: "assets/img/cases/x-foto.webp", video: "" }
  ]
};
