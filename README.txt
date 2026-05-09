VComInt CPT Builder
===================

Versão: 1.1.0
Autor: VComInt

Plugin para criar Custom Post Types e metacampos salvos em postmeta.

Novidades da versão 1.1.0
-------------------------

- Adiciona o tipo de metacampo "Relação com CPT".
- Permite configurar um CPT relacionado, por exemplo: ferramentas.
- Permite seleção única ou múltipla.
- A relação salva IDs de posts, não títulos.
- A lista de opções é carregada automaticamente a partir dos posts do CPT relacionado.
- Quando uma relação aponta para o CPT ferramentas, o plugin recalcula automaticamente as contagens esperadas pelos templates atuais:
  - tool_usage_count
  - tool_company_count
  - tool_freelancer_count

Uso recomendado
---------------

No CPT diretorio, crie um metacampo assim:

Meta key: ferramentas_usadas
Label: Ferramentas usadas
Tipo: Relação com CPT
CPT relacionado: ferramentas
Múltipla: Sim

Com isso, cada perfil do diretório poderá selecionar ferramentas criadas no CPT ferramentas. Ao salvar um perfil, o plugin recalcula quantas empresas e freelancers usam cada ferramenta.

Compatibilidade
---------------

A versão mantém o mesmo option name usado anteriormente:

vcomint_cpt_builder_items

Isso preserva os CPTs e metacampos já configurados.

Observações
-----------

O cálculo das ferramentas usa o campo tipo do perfil de diretório:

Empresa -> soma em tool_company_count
Freelancer -> soma em tool_freelancer_count

O total é salvo em tool_usage_count.
