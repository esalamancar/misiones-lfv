import sys
import argparse
import csv
import json
import requests
from io import StringIO


def get_sheet_csv(sheet_id, sheet_name):
    """Obtiene datos de una hoja de cálculo pública como CSV"""
    url = f"https://docs.google.com/spreadsheets/d/{sheet_id}/gviz/tq?tqx=out:csv&sheet={sheet_name}"

    try:
        response = requests.get(url)
        response.raise_for_status()
        return response.text
    except requests.exceptions.RequestException as e:
        return {"error": str(e)}


def check_filters(csv_data, rows_to_check=2):
    """Verifica si hay filtros aplicados en las primeras filas"""
    reader = csv.reader(StringIO(csv_data))
    rows = list(reader)

    if len(rows) < rows_to_check:
        return {"warning": f"Solo hay {len(rows)} filas, no se pueden verificar {rows_to_check} filas"}

    for i in range(rows_to_check):
        if i < len(rows):
            row = rows[i]
            if any(cell.strip() == '' for cell in row):
                return {"warning": f"Posible filtro aplicado en la fila {i + 1}"}

    return {"status": "OK", "message": "No se detectaron filtros en las primeras filas"}


def extract_columns(csv_data, columns):
    """Extrae las columnas especificadas"""
    reader = csv.reader(StringIO(csv_data))
    result = []
    column_indices = [int(col.strip()) for col in columns.split(',')]

    for row in reader:
        extracted_row = {}
        for i, col_idx in enumerate(column_indices):
            # Verificar que el índice de columna sea válido
            if col_idx < len(row):
                extracted_row[f"columna_{col_idx}"] = row[col_idx]
            else:
                extracted_row[f"columna_{col_idx}"] = None
        result.append(extracted_row)

    return result


def main():
    parser = argparse.ArgumentParser(description='Extrae datos de Google Sheets sin API')
    parser.add_argument('--sheet_id', required=True, help='ID de la hoja de cálculo')
    parser.add_argument('--sheet_name', required=True, help='Nombre de la hoja')
    parser.add_argument('--columns', required=True,
                        help='Columnas a extraer (números separados por comas, ej: "1,9" para B y J)')

    args = parser.parse_args()

    # Obtener datos de la hoja como CSV
    csv_data = get_sheet_csv(args.sheet_id, args.sheet_name)

    if isinstance(csv_data, dict) and 'error' in csv_data:
        print(json.dumps({"error": csv_data['error']}, ensure_ascii=False))
        sys.exit(1)

    # Verificar filtros
    filter_check = check_filters(csv_data)

    # Extraer columnas especificadas
    extracted_data = extract_columns(csv_data, args.columns)

    # Preparar resultado final
    result = {
        "metadata": {
            "sheet_id": args.sheet_id,
            "sheet_name": args.sheet_name,
            "columns_extracted": args.columns.split(','),
            "filter_check": filter_check,
            "total_rows": len(extracted_data)
        },
        "data": extracted_data
    }

    # Imprimir resultado como JSON
    print(json.dumps(result, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
