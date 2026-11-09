
import json
import time
import urllib.request
import urllib.parse
from collections import defaultdict
from concurrent.futures import ThreadPoolExecutor, as_completed

BASE_URL = "http://localhost:8080"
TOTAL_REQUESTS = 18
MAX_WORKERS = 6

ALGORITHMS = {
    "round_robin": "ROUND ROBIN",
    "least_connections": "LEAST CONNECTIONS",
    "least_response_time": "LEAST RESPONSE TIME"
}


def reset_state():
    import subprocess

    subprocess.run(
        [
            "docker", "compose", "exec", "-T",
            "balanceador", "sh", "-c",
            "rm -f /tmp/shopnow_lb_state.json"
        ],
        check=True,
        capture_output=True
    )


def make_request(number, algorithm):
    params = urllib.parse.urlencode({"algorithm": algorithm})
    url = f"{BASE_URL}/?{params}"
    started = time.perf_counter()

    try:
        with urllib.request.urlopen(url, timeout=15) as response:
            data = json.loads(response.read().decode("utf-8"))

        elapsed = (time.perf_counter() - started) * 1000

        return {
            "number": number,
            "server": data.get("server", "Desconhecido"),
            "delay": data.get("artificial_delay_ms", 0),
            "elapsed": elapsed,
            "status": response.status,
            "error": None
        }

    except Exception as error:
        return {
            "number": number,
            "server": "ERRO",
            "delay": 0,
            "elapsed": 0,
            "status": 0,
            "error": str(error)
        }


for algorithm, title in ALGORITHMS.items():
    print("\n" + "=" * 65)
    print(f" SIMULAÇÃO CONCORRENTE: {title}")
    print("=" * 65)

    reset_state()
    print(f"Enviando {TOTAL_REQUESTS} requisições com "
          f"{MAX_WORKERS} trabalhadores simultâneos.\n")

    distribution = defaultdict(int)
    errors = 0

    with ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
        futures = [
            executor.submit(make_request, number, algorithm)
            for number in range(1, TOTAL_REQUESTS + 1)
        ]

        for future in as_completed(futures):
            result = future.result()

            if result["error"]:
                errors += 1
                print(
                    f"Requisição {result['number']:02d} | "
                    f"ERRO: {result['error']}"
                )
                continue

            distribution[result["server"]] += 1

            print(
                f"Requisição {result['number']:02d} | "
                f"{result['server']:<12} | "
                f"{result['elapsed']:8.2f} ms | "
                f"Atraso: {result['delay']:3} ms | "
                f"HTTP {result['status']}"
            )

    print("\nRESUMO DA DISTRIBUIÇÃO")

    for server, count in sorted(distribution.items()):
        percentage = count / TOTAL_REQUESTS * 100

        print(
            f"{server:<12} | "
            f"{count:02d} requisições | "
            f"{percentage:.1f}%"
        )

    print(f"\nTotal de erros: {errors}")

print("\n" + "=" * 65)
print(" SIMULAÇÃO CONCORRENTE FINALIZADA")
print("=" * 65)
