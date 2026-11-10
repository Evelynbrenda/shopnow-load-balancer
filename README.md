# ShopNow — Load Balancer em PHP

## 1. Sobre o projeto

Projeto acadêmico de Sistemas Distribuídos que implementa um balanceador de carga para uma aplicação fictícia de e-commerce chamada ShopNow.

O balanceador distribui requisições entre três instâncias de servidores, utilizando três algoritmos de distribuição e verificando a disponibilidade das instâncias antes de encaminhar as requisições.

O projeto demonstra conceitos de balanceamento de carga, concorrência, monitoramento de disponibilidade e tolerância a falhas.

## 2. Tecnologias

- PHP 8.3
- Apache
- Docker e Docker Compose
- Python 3
- Apache Benchmark (`ab`)
- SQLite não utilizado neste projeto.

## 3. Arquitetura

A aplicação possui quatro containers:

- **Balanceador:** recebe as requisições na porta 8080.
- **Servidor 1:** instância da aplicação na porta 8081.
- **Servidor 2:** instância da aplicação na porta 8082.
- **Servidor 3:** instância da aplicação na porta 8083.

O balanceador verifica a saúde das instâncias disponíveis e encaminha cada requisição para o servidor selecionado.

### Atrasos artificiais

Para simular servidores com capacidades diferentes, foram configurados atrasos artificiais:

| Servidor | Atraso configurado |
|---|---:|
| Servidor 1 | 30 ms |
| Servidor 2 | 150 ms |
| Servidor 3 | 300 ms |

Esses atrasos permitem observar como os algoritmos se comportam diante de servidores com tempos de resposta diferentes.

## 4. Como executar

Na pasta do projeto, execute:

```bash
docker compose up --build -d
```

### Endereços

- Balanceador: http://localhost:8080
- Servidor 1: http://localhost:8081
- Servidor 2: http://localhost:8082
- Servidor 3: http://localhost:8083

Para verificar os containers:

```bash
docker compose ps
```

## 5. Algoritmos de balanceamento

O projeto implementa três algoritmos:

### 5.1 Round Robin

Distribui as requisições alternadamente entre os servidores saudáveis, mantendo um índice para determinar o próximo servidor.

**Acessar:**

```text
http://localhost:8080/?algorithm=round_robin
```

### 5.2 Least Connections

Seleciona o servidor que possui a menor quantidade de requisições em andamento.

O contador de conexões é incrementado antes do encaminhamento e decrementado quando a requisição termina.

Em caso de empate, utiliza alternância entre os servidores candidatos.

**Acessar:**

```text
http://localhost:8080/?algorithm=least_connections
```

### 5.3 Least Response Time

Seleciona o servidor com o menor tempo médio de resposta registrado.

O balanceador atualiza as médias utilizando uma média móvel exponencial, permitindo que medições recentes influenciem a escolha.

Servidores ainda sem histórico de medição recebem requisições iniciais para que o balanceador possa coletar seus tempos de resposta.

**Acessar:**

```text
http://localhost:8080/?algorithm=least_response_time
```

## 6. Verificação de disponibilidade

Antes de selecionar uma instância, o balanceador realiza uma verificação HTTP dos servidores por meio do Health Check.

A verificação utiliza um endpoint específico que informa se o servidor está online, sem aplicar o atraso artificial.

Caso uma instância esteja indisponível, ela é removida da lista de servidores saudáveis e não participa da seleção.

Se nenhum servidor estiver saudável, o balanceador retorna HTTP 503.

## 7. Simulação de falha

Para simular a indisponibilidade do Servidor 3:

```bash
docker compose stop servidor3
```

Execute a simulação:

```bash
python3 testes/simular_balanceamento.py
```

O balanceador deverá continuar encaminhando as requisições para os servidores disponíveis.

Para restaurar o servidor:

```bash
docker compose start servidor3
```

Durante o teste realizado, o Servidor 3 permaneceu indisponível e os outros dois servidores continuaram atendendo às requisições, sem erros.

## 8. Testes automatizados

Os algoritmos possuem testes automatizados implementados em PHP puro.

Para executar:

```bash
make test
```

**Resultado obtido:**

- 11 testes aprovados.
- 0 testes reprovados.

Os testes verificam o funcionamento do Round Robin, Least Connections e Least Response Time, incluindo situações de empate, ausência de medições e atualização das médias de resposta.

## 9. Simulações de balanceamento

O projeto possui dois scripts em Python para simular o comportamento dos algoritmos.

### 9.1 Simulação sequencial

Arquivo:

```text
testes/simular_balanceamento.py
```

Execução:

```bash
python3 testes/simular_balanceamento.py
```

São realizadas 12 requisições sequenciais para cada algoritmo.

**Resultados obtidos:**

| Algoritmo | Servidor 1 | Servidor 2 | Servidor 3 |
|---|---:|---:|---:|
| Round Robin | 4 | 4 | 4 |
| Least Connections | 4 | 4 | 4 |
| Least Response Time | 10 | 1 | 1 |

O Round Robin distribuiu igualmente as requisições.

O Least Connections também apresentou distribuição equilibrada porque as requisições eram sequenciais e as conexões eram liberadas antes da próxima seleção.

O Least Response Time priorizou o servidor com menor tempo médio de resposta.

### 9.2 Simulação concorrente

Arquivo:

```text
testes/simular_concorrencia.py
```

Execução:

```bash
python3 testes/simular_concorrencia.py
```

Foram realizadas 18 requisições com até 6 trabalhadores simultâneos.

**Resultados obtidos:**

| Algoritmo | Servidor 1 | Servidor 2 | Servidor 3 |
|---|---:|---:|---:|
| Round Robin | 6 | 6 | 6 |
| Least Connections | 12 | 4 | 2 |
| Least Response Time | 9 | 3 | 6 |

A simulação demonstrou comportamentos diferentes:

- **Round Robin:** manteve a distribuição uniforme, sem considerar a velocidade dos servidores.
- **Least Connections:** direcionou mais requisições ao Servidor 1, que conclui suas requisições mais rapidamente e volta a ficar disponível.
- **Least Response Time:** priorizou servidores com melhores tempos médios registrados.

Nas simulações realizadas, todas as requisições foram concluídas com HTTP 200 e sem erros.

## 10. Testes de carga com Apache Benchmark

Os testes de desempenho também foram realizados com Apache Benchmark, utilizando um container Alpine com a ferramenta instalada.

### Parâmetros utilizados

- Total de requisições: 1.000.
- Concorrência: 50 requisições simultâneas.
- Servidores: três instâncias.
- Ferramenta: Apache Benchmark (`ab`).

### Round Robin

```bash
docker run --rm --network shopnow-load-balancer_default alpine:3.20 sh -c "apk add --no-cache apache2-utils >/dev/null && ab -n 1000 -c 50 'http://balanceador/?algorithm=round_robin'"
```

### Least Connections

```bash
docker run --rm --network shopnow-load-balancer_default alpine:3.20 sh -c "apk add --no-cache apache2-utils >/dev/null && ab -n 1000 -c 50 'http://balanceador/?algorithm=least_connections'"
```

### Resultados obtidos

| Métrica | Round Robin | Least Connections |
|---|---:|---:|
| Requisições concluídas | 1.000 | 1.000 |
| Falhas reais (Connect / Receive / Exceptions / não-2xx) | 0 | 0 |
| "Failed requests" do ab (todas do tipo `Length`) | 666 | 301 |
| Tempo total | 7,691 s | 6,356 s |
| Requisições por segundo | 130,02 | 157,33 |
| Tempo médio por requisição | 384,550 ms | 317,800 ms |
| Mediana | 350 ms | 266 ms |
| 95% das requisições em até | 790 ms | 621 ms |
| Maior tempo | 1.032 ms | 921 ms |

### Sobre o "Failed requests"

O `ab` usa o tamanho da primeira resposta como referência (161 bytes) e conta como falha qualquer resposta de tamanho diferente. O Servidor 1 responde `"artificial_delay_ms": 30` e os Servidores 2 e 3 respondem `150` e `300`, um dígito a mais. Então essas "falhas" são só respostas dos Servidores 2 e 3, todas com HTTP 200.

Isso acaba mostrando a distribuição:

- Round Robin: 666 de 1.000, exatamente 2/3 para os Servidores 2 e 3.
- Least Connections: 301 de 1.000, ou seja, cerca de 70% foi para o Servidor 1.

### Análise dos resultados

Nessa execução o Least Connections foi melhor em todas as métricas: cerca de 21% mais requisições por segundo e mediana 84 ms menor.

O motivo é que o Round Robin manda 1/3 das requisições para o Servidor 3 (300 ms) mesmo quando ele já está ocupado. O Least Connections manda para quem tem menos requisições em andamento, e como o Servidor 1 termina rápido, ele quase sempre é o escolhido.

Os números variam entre execuções (numa execução anterior, os dois ficaram perto de 93 a 94 req/s), então o mais confiável é a comparação feita no mesmo momento e na mesma máquina, como nesta tabela.

## 11. Considerações técnicas

O estado compartilhado do balanceador utiliza um arquivo temporário e `flock` para coordenar o acesso entre processos Apache.

O projeto possui finalidade didática e demonstra conceitos de distribuição de carga, concorrência, disponibilidade e tolerância a falhas.

Não substitui um proxy ou balanceador de carga preparado para ambientes de produção.

## 12. Encerrar a aplicação

Para parar e remover os containers:

```bash
docker compose down
```
