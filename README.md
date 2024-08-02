# TimCropper

O **TimCropper** é um script PHP desenvolvido para facilitar o redimensionamento dinâmico de imagens em um servidor web. Ele permite que imagens sejam redimensionadas automaticamente conforme as especificações fornecidas via parâmetros de URL, sem a necessidade de edição manual ou uso de ferramentas externas. Além disso, o script gerencia o armazenamento em cache das imagens redimensionadas, melhorando a eficiência e reduzindo o tempo de carregamento das páginas.

## Finalidade

A principal finalidade do **TimCropper** é oferecer uma solução simples e eficiente para adaptar imagens a diferentes tamanhos, atendendo às necessidades de diversos dispositivos e layouts de páginas web. Isso é particularmente útil em sites responsivos onde as imagens precisam ser dimensionadas adequadamente para diferentes resoluções de tela. Ao gerenciar automaticamente o cache das imagens redimensionadas, o script também ajuda a minimizar a carga no servidor e a otimizar o desempenho do site.

# ATENÇÃO

Embora esse projeto, ofereça diversos recursos de proteção para mitigação de ataques, ele não está totalmente isento. Pois, ainda existem formas de saturar o servidor no qual esse projeto está armazenado. Semelhante a outras ferramentas já descontinuadas por esse mesmo motivo.

Sendo assim, recomendamos fortemente buscar outras formas mais seguras de gerir suas imagens, mas, caso esteja em busca de uma solução com o maior performance e para substituir uma certa solução que a muito foi descontinuada, essa solução irá lhe atender, basta apenas mudar o nome do arquivo. :)

## Documentação

### Visão Geral

O **TimCropper** é um script PHP que permite o redimensionamento dinâmico de imagens e gerenciamento de cache. Suporta formatos de saída JPG, PNG e WebP com base na configuração e disponibilidade das extensões GD. Este manual fornece instruções para configurar e utilizar o script.

### Requisitos

- Extensão PHP GD habilitada;
- Extensão `mime_content_type` habilitada;
- Extensão PHP GD WebP para saída em formato WebP (caso vá utilizar o formato webp);
- Referências de imagens externas são bloqueadas por razões de segurança;

### Configuração

Caso queira modificar as configurações de uso, você pode personalizar valores padrão criando um arquivo `timcropper-config.php` no mesmo diretório. As constantes disponíveis para personalização são:

- **FOLDER_DEFAULT**: Nome da pasta padrão para armazenar arquivos de imagem modificados;
- **QUALITY**: Nível de qualidade da imagem (para JPG e WebP) (de 0 a 100);
- **COMPRESSOR**: Nível de compressão (para PNG), de 0 a 9, onde 9 será o arquivo de menor também e com perda considerável de qualidade;
- **WIDTH**: Largura padrão quando não especificada;
- **MIN_WIDTH**: Largura minima de imagem;
- **MIN_HEIGHT**: Altura minima de imagem;
- **MAX_WIDTH**: Largura máxima de imagem;
- **MAX_HEIGHT**: Altura máxima de imagem;
- **AUTO_CLEAN**: Ativar limpeza automática da pasta de cache;
- **AUTO_CLEAN_DAYS**: Intervalo em dias para limpeza automática da pasta de cache;
- **OUTPUT_FORMAT**: Formato de saída padrão ('jpg', 'png', 'webp');

### Uso

Para redimensionar uma imagem, passe os parâmetros `src` (caminho da imagem), `w` (largura) e opcionalmente `h` (altura) como parâmetros GET.

**Exemplo de URL:**

```bash
http://seusite.com/timcropper.php?src=path/to/image.jpg&w=400&h=300
```

### Configuração Passo a Passo

1- **Habilite a Extensão GD no PHP:**

Verifique se a extensão GD está habilitada no seu arquivo `php.ini`.

```bash
"extension=gd"
```

2 - **Crie o Arquivo `timcropper-config.php` (Opcional):**

Crie um arquivo `timcropper-config.php` no mesmo diretório do `timcropper.php` para personalizar as configurações:

```bash

<?php
define('FOLDER_DEFAULT', 'cache'); // Nome da pasta padrão
define('QUALITY', 75); // Nível de qualidade da imagem JPG/WebP
define('COMPRESSOR', 6); // Nível de compressão da imagem PNG
define('WIDTH', 800); // Largura padrão
define('MIN_WIDTH', 50); // Default min width
define('MIN_HEIGHT', 50); // Default min height
define('MAX_WIDTH', 2560); // Default max width
define('MAX_HEIGHT', 2000); // Default max height
define('AUTO_CLEAN', true); // Ativar limpeza automática
define('AUTO_CLEAN_DAYS', 30); // Intervalo em dias para limpeza automática
define('OUTPUT_FORMAT', 'webp'); // Formato de saída padrão
?>
```

3 - **Faça o Upload do Arquivo `timcropper.php`:**

Faça o upload do arquivo `timcropper.php` para o seu servidor web.

4 - **Permissões da Pasta de Cache:**

Embora a pasta de cache seja criada automaticamente pelo script com as devidas permissões, caso haja problemas, certifique-se de que a pasta de cache tenha permissões de gravação.

```bash
chmod 755 cache
```

### Exemplo de Uso

Suponha que você tenha uma imagem localizada em `images/photo.jpg` e queira redimensioná-la para uma largura de 400px e uma altura de 300px.

**URL:**

```bash
http://seusite.com/timcropper.php?src=images/photo.jpg&w=400&h=300
```

Ao acessar essa URL, o script `timcropper.php` irá:

1.  Verificar se a extensão GD está habilitada;
2.  Verificar se a extensão mime_content_type está habilitada;
3.  Carregar a configuração do arquivo `timcropper-config.php` se disponível;
4.  Validar o caminho da imagem e os parâmetros fornecidos;
5.  Redimensionar a imagem conforme os parâmetros `w` e `h`;
6.  Armazenar a imagem redimensionada na pasta de cache;
7.  Exibir a imagem redimensionada no navegador.

### Debug e Solução de Problemas

- **Erro "GD extension disabled":**
  Verifique se a extensão GD está habilitada no seu arquivo `php.ini` e reinicie o servidor web.
- **Erro "mime_content_type function is not available":**
  Verifique se a extensão `mime_content_type` está habilitada no seu arquivo `php.ini` e reinicie o servidor web.
- **Image not found or invalid parameter:**
  Verifique se o caminho da imagem fornecido no parâmetro `src` está correto e acessível pelo servidor.
- **Permissões da Pasta de Cache:**
  Certifique-se de que a pasta de cache tenha permissões de gravação adequadas.

### Observação Importante

Caso o **AUTO_CLEAN** esteja habilitado, eventualmente, caso durante a execução, o script encontre arquivos inválidos, irá verificar se atende o requisito de intervalo e executará a limpeza da pasta de cache automaticamente. Evitando assim que seu servidor seja sobrecarregado com arquivos que não são mais necessários.

Porém, caso acredite ser necessário, pode execultar a limpeza manualmente através da url abaixo:

```bash
http://seusite.com/timcropper.php?clear=true
```

Com este manual, você deve ser capaz de configurar e utilizar o `timcropper.php` para redimensionar e gerenciar o cache de suas imagens dinamicamente.

## Contribuição

Por favor, veja [CONTRIBUIÇÃO](https://github.com/evertecdigital/timcropper/blob/master/CONTRIBUTING.md) para maiores detalhes.

## Suporte

Se você descobrir algum problema relacionado à segurança, envie um e-mail para suporte@evertecdigital.com.br em vez de usar o **Issues**.

## Creditos

- [Everson Aguiar](https://github.com/eversonaguiar) (Desenvolvedor)
- [Evertec Digital](https://github.com/evertecdigital) (Business)
- [All Contributors](https://github.com/evertecdigital/timcropper/contributors) (This Project)

## Licença

A licença MIT (MIT). Consulte Arquivo de [Licença](https://github.com/evertecdigital/timcropper/blob/master/LICENSE) para obter mais informações.
