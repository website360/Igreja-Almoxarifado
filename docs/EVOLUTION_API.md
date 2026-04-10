# Integração Evolution API - Documentação Completa

## 📋 Visão Geral

O sistema agora suporta **dois provedores de WhatsApp**:
- **Evolution API** (Recomendado) - Gratuito, integrado, conexão por QR Code
- **Z-API** (Avançado) - Serviço pago, requer credenciais próprias

## 🔧 Configuração para Administradores

### Passo 1: Configurar Evolution API no Sistema

1. Acesse **Configurações → Evolution API**
2. Preencha:
   - **URL da Evolution API**: `https://seu-servidor.com:8080`
   - **API Key Global**: Sua chave `AUTHENTICATION_API_KEY`
3. Clique em **Salvar**

### Onde encontrar as credenciais?

**Se você instalou via Docker:**
```bash
# Verifique o comando docker run ou docker-compose.yml
docker inspect evolution-api | grep AUTHENTICATION_API_KEY
```

**Se você usa Evolution API hospedada:**
- URL: Fornecida pelo provedor
- API Key: Fornecida pelo provedor no painel

### Exemplo de instalação Evolution API (Docker)

```bash
docker run -d \
  --name evolution-api \
  -p 8080:8080 \
  -e AUTHENTICATION_API_KEY=SUA_CHAVE_AQUI \
  atendai/evolution-api:latest
```

Depois configure no sistema:
- URL: `http://seu-ip:8080`
- API Key: `SUA_CHAVE_AQUI`

## 👥 Uso pelos Usuários

### Conectar WhatsApp com Evolution API

1. Acesse **Integrações**
2. Escolha **Evolution API** (card verde)
3. Digite um nome para a instância (ex: `igreja-sede`)
4. Clique em **Criar Instância e Gerar QR Code**
5. Abra WhatsApp no celular
6. Vá em **Menu (⋮) → Aparelhos conectados → Conectar**
7. Escaneie o QR Code na tela
8. Aguarde... quando conectar aparecerá **"WhatsApp Conectado!"**

### Conectar WhatsApp com Z-API

1. Acesse **Integrações**
2. Escolha **Z-API** (card azul)
3. Preencha:
   - Instance ID
   - Token
   - Client Token (Security)
4. Marque **Integração ativa**
5. Clique em **Salvar**

## 🏗️ Arquitetura Técnica

### Arquivos Criados/Modificados

```
includes/EvolutionAPI.php          # Classe de serviço Evolution API
config/app.php                     # Constantes (fallback)
configuracoes/index.php            # Área de config para admin
integracoes/index.php              # Interface com seletor de provedor
integracoes/api.php                # Endpoints backend
database/migrations/               # Migrations do banco
```

### Tabela `whatsapp_integrations`

Novos campos adicionados:
- `instance_name` - Nome da instância Evolution
- `connection_status` - Status da conexão (connecting, open, close)
- `phone_connected` - Número conectado
- `provider` - Provedor (evolution ou zapi)

### Endpoints API

**Evolution API:**
- `POST /integracoes/api.php?action=set_provider` - Trocar provedor
- `POST /integracoes/api.php?action=evo_create_instance` - Criar instância
- `POST /integracoes/api.php?action=evo_get_qrcode` - Buscar QR Code
- `POST /integracoes/api.php?action=evo_check_status` - Verificar status
- `POST /integracoes/api.php?action=evo_logout` - Desconectar

**Ambos provedores:**
- `POST /integracoes/api.php?action=test_whatsapp` - Enviar teste (detecta provedor automaticamente)

### Classe EvolutionAPI

Métodos disponíveis:
```php
$evo = new EvolutionAPI();

// Criar instância
$evo->createInstance('igreja-sede');

// Obter QR Code
$evo->getQrCode('igreja-sede');

// Verificar status
$evo->getConnectionStatus('igreja-sede');

// Enviar mensagem
$evo->sendText('igreja-sede', '5511999999999', 'Olá!');

// Desconectar
$evo->logoutInstance('igreja-sede');

// Deletar instância
$evo->deleteInstance('igreja-sede');
```

## 🔄 Fluxo de Funcionamento

### Evolution API

1. **Admin configura** URL e API Key em Configurações
2. **Usuário cria instância** via interface
3. **Sistema chama** Evolution API para criar instância
4. **Evolution retorna** QR Code
5. **Interface mostra** QR Code e atualiza a cada 30s
6. **JavaScript verifica** status a cada 5s
7. **Quando conectado** para verificação e mostra tela verde
8. **Envio de mensagens** usa `sendText()` da Evolution API

### Z-API

1. **Usuário preenche** credenciais próprias
2. **Sistema salva** no banco
3. **Envio de mensagens** usa endpoint Z-API diretamente

## 🧪 Teste de Envio

Ambos provedores suportam teste de envio:

1. Vá em **Integrações**
2. Role até **Testar Envio de Mensagem**
3. Digite número (apenas números, com DDD)
4. Digite mensagem
5. Clique em **Enviar Teste**

O sistema detecta automaticamente qual provedor está ativo e usa o método correto.

## 🔒 Segurança

- API Key da Evolution armazenada criptografada no banco
- Validação de permissões em todos endpoints
- CSRF token em todos formulários
- Auditoria de todas ações (criar instância, desconectar, etc)

## 📊 Monitoramento

### Status da Conexão

A interface mostra em tempo real:
- **Verificando...** (cinza) - Carregando
- **Desconectado** (cinza) - Sem conexão
- **Conectado** (verde) - WhatsApp ativo

### Log de Mensagens

Todas mensagens enviadas (por ambos provedores) são registradas em `message_queue` com:
- Status (pending, sent, failed)
- Tentativas
- Erros (se houver)
- Timestamp

## 🐛 Troubleshooting

### "Evolution API não configurada"

**Causa:** Admin não configurou URL/API Key  
**Solução:** Vá em Configurações → Evolution API e configure

### "Erro ao criar instância"

**Causas possíveis:**
- URL da Evolution incorreta
- API Key incorreta
- Evolution API offline
- Firewall bloqueando conexão

**Solução:** Verifique configurações e conectividade

### QR Code não aparece

**Causas possíveis:**
- Instância já existe
- Erro na Evolution API
- Timeout de conexão

**Solução:** Tente desconectar e criar novamente

### "Client-Token não configurado" (Z-API)

**Causa:** Campo Client Token vazio  
**Solução:** Preencha o Client Token no formulário Z-API

## 📈 Próximas Melhorias

- [ ] Webhook para receber mensagens
- [ ] Envio de mídia (imagens, documentos)
- [ ] Grupos WhatsApp
- [ ] Agendamento de mensagens
- [ ] Dashboard de métricas
- [ ] Backup automático de conversas

## 🆘 Suporte

Para problemas técnicos:
1. Verifique logs em `Configurações → Auditoria`
2. Teste conexão com Evolution API manualmente
3. Verifique permissões do usuário
4. Consulte documentação oficial da Evolution API

---

**Versão:** 1.0  
**Última atualização:** Abril 2026  
**Desenvolvido para:** Sistema Igreja Conectada
