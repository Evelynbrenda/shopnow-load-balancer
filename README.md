# ShopNow — Load Balancer em PHP

## Tecnologias
- PHP 8.3 + Apache
- Docker Compose
- Apache Benchmark (`ab`)

## Subir
```powershell
docker compose up --build -d
```

## Endereços
- Balanceador: http://localhost:8080
- Servidores: http://localhost:8081, 8082 e 8083

## Algoritmos
- Round Robin: http://localhost:8080/?algorithm=round_robin
- Least Connections: http://localhost:8080/?algorithm=least_connections

Round Robin alterna entre servidores saudáveis. Least Connections escolhe aquele com menos requisições em andamento. O contador é incrementado antes do encaminhamento e decrementado ao finalizar.

## Simular falha
```powershell
docker compose stop servidor2
curl.exe "http://localhost:8080/?algorithm=round_robin"
curl.exe "http://localhost:8080/?algorithm=least_connections"
docker compose start servidor2
```

## Teste de carga (se `ab` estiver instalado)
```powershell
ab -n 1000 -c 50 "http://localhost:8080/?algorithm=round_robin"
ab -n 1000 -c 50 "http://localhost:8080/?algorithm=least_connections"
```
`-n` é o total de requisições; `-c` é o número de requisições concorrentes.

## Encerrar
```powershell
docker compose down
```

## Observação
Projeto didático. A contagem compartilhada usa arquivo com `flock` para coordenar processos Apache. Não substitui um proxy de produção.
