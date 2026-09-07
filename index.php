<?php
include_once __DIR__ . '/header.php';
require_once __DIR__ . '/vendor/autoload.php';

use Google\Client as Google_Client;
use Google\Service\Sheets as Google_Service_Sheets;
use Google\Service\Sheets\ValueRange as Google_Service_Sheets_ValueRange;

function executePythonScript($sheet_id, $sheet_name, $column1, $column2) {
    $command = 'python3 /var/www/html/extract_key_value.py --sheet_id "' . $sheet_id . '" --sheet_name "' . $sheet_name . '" --columns "' . $column1 . ',' . $column2 . '" 2>&1';
    $output = shell_exec($command);

    if (empty($output)) {
        return null;
    }

    $data = json_decode($output, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return $data;
}

$dataMisiones = executePythonScript("1YeE0w_1HpB7luCCFrasJ-jFZN3h2-H-sEQwKcFHJ--c", "Misiones", "0", "1");
$dataActores = executePythonScript("1kiUjnIrdlN6wnFDETshi3Qr8unFgPP4CypWtNQX0Aek", "Mapas de actores", "0", "1");

if (!isset($dataMisiones['data']) || !is_array($dataMisiones['data'])) {
    die("Error: Los datos de misiones no tienen el formato esperado");
}

$misiones = array_slice($dataMisiones['data'], -3);
arsort($misiones);

$dropdownOptions = [];
$checkboxOptions = [];

if (isset($data['data']) && is_array($data['data'])) {
    foreach ($data['data'] as $item) {
        $col1 = $item['columna_1'] ?? null;
        $col13 = $item['columna_4'] ?? null;

        if ($col1) {
            if (!in_array($col1, $dropdownOptions)) {
                $dropdownOptions[] = $col1;
            }

            if ($col13) {
                if (!isset($checkboxOptions[$col1])) {
                    $checkboxOptions[$col1] = [];
                }
                $checkboxOptions[$col1][] = $col13;
            }
        }
    }
}

$actoresDropdown = [];
if (isset($dataActores['data']) && is_array($dataActores['data'])) {
    foreach ($dataActores['data'] as $actor) {
        if (isset($actor['columna_1'])) {
            $actoresDropdown[] = $actor['columna_1'];
        }
    }
    $actoresDropdown = array_unique($actoresDropdown);
    $actoresDropdown = array_slice($actoresDropdown, 1);
    sort($actoresDropdown, SORT_STRING | SORT_FLAG_CASE);
}

function writeToGoogleSheet($spreadsheetId, $range, $values) {
    try {
        $credentialsPath = '/var/secrets/google/credentials.json';

        $client = new Google_Client();
        $client->setApplicationName('Google Sheets API PHP');
        $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
        $client->setAuthConfig($credentialsPath);
        $client->setAccessType('offline');

        $service = new Google_Service_Sheets($client);

        $body = new Google_Service_Sheets_ValueRange([
            'values' => $values
        ]);

        $params = [
            'valueInputOption' => 'RAW'
        ];

        $result = $service->spreadsheets_values->append(
            $spreadsheetId,
            $range,
            $body,
            $params
        );

        return true;
    } catch (Exception $e) {
        error_log('Error al escribir en Google Sheets: ' . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mision = $_POST['mision'] ?? '';
    $actor = $_POST['actor'] ?? '';
    $nombre = $_POST['nombre'] ?? '';
    $opciones = $_POST['opciones'] ?? [];

    $opcionesUnidas = implode('|', $opciones);

    $misionParts = explode('|', $mision);
    $sheetData = [
        [
            date('Y-m-d H:i:s'),
            $misionParts[0],
            $misionParts[1],
            $actor,
            $nombre,
            $opcionesUnidas
        ]
    ];

    $resultadoSheet = writeToGoogleSheet(
        '1kiUjnIrdlN6wnFDETshi3Qr8unFgPP4CypWtNQX0Aek',
        'Participación Misiones!A:E',
        $sheetData
    );

    if ($resultadoSheet) {
        echo "<script>alert('✅ Registro guardado exitosamente.'); window.location.href = window.location.href;</script>";
    } else {
        echo "<script>alert('❌Error al guardar el registro'); window.location.href = window.location.href;</script>";
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Misiones LFV - 🆇</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .select-container {
            position: relative;
            margin-bottom: 15px;
        }

        #nombre {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .select-search {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: none;
            z-index: 1000;
        }

        .select-search input {
            width: 100%;
            padding: 8px;
            border: none;
            border-bottom: 1px solid #ddd;
            box-sizing: border-box;
            outline: none;
        }

        .select-search .options {
            max-height: 200px;
            overflow-y: auto;
        }

        .select-search .option {
            padding: 8px;
            cursor: pointer;
        }

        .select-search .option:hover {
            background-color: #f5f5f5;
        }

        .select-search .option.selected {
            background-color: #e3e3e3;
        }

        @media (max-width: 600px) {
          select,
          .select-container select {
            max-width: 100%;
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
          }
          select option {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 100vw;
            display: block;
          }
        }
    </style>
</head>
<body>
    <?= getHeader('Reporte Misiones LFV - 🆇') ?>
    <main>
        <section class="search-section">
            <form class="search-form" action="" method="POST" style="display: grid;" onsubmit="return handleSubmit(this);">
                <label for="mision">Seleccione la misión:</label>
                <select name="mision" id="mision" required>
                    <option value="">Seleccione una misión</option>
                    <?php
                    if (!empty($misiones)) {
                        foreach ($misiones as $mision): ?>
                            <option value="<?= htmlspecialchars($mision['columna_0']) ?>|<?= htmlspecialchars($mision['columna_1']) ?>">
                                <?= htmlspecialchars($mision['columna_0']) ?> - <?= htmlspecialchars($mision['columna_1']) ?>
                            </option>
                        <?php endforeach;
                    } else {
                        echo "<option value=''>No hay misiones disponibles</option>";
                    }
                    ?>
                </select>

                <label for="actor">Seleccione el actor:</label>
                <select name="actor" id="actor" required onchange="fetchNombresPorActor(this.value)">
                    <option value="">Seleccione un actor</option>
                    <?php
                    if (!empty($actoresDropdown)) {
                        foreach ($actoresDropdown as $actor): ?>
                            <option value="<?= htmlspecialchars($actor) ?>">
                                <?= htmlspecialchars($actor) ?>
                            </option>
                        <?php endforeach;
                    } else {
                        echo "<option value=''>No hay actores disponibles</option>";
                    }
                    ?>
                </select>

                <label for="nombre">Seleccione su nombre:</label>
                <div class="select-container">
                    <select name="nombre" id="nombre" required onchange="updateCheckboxes(this.value)" onclick="toggleSearch(event)">
                        <option value="">Seleccione un nombre</option>
                    </select>
                    <div class="select-search" id="nombreSearch">
                        <input type="text" placeholder="Buscar nombre..." onkeyup="filterNombres()">
                        <div class="options">
                        </div>
                    </div>
                </div>

                <div id="checkbox-section" style="display: none; margin-top: 15px;">
                    <label>Seleccione las opciones disponibles:</label>
                    <div id="checkbox-container"></div>
                </div>

                <button type="submit" id="submitButton">Registrar</button>
            </form>
        </section>

        <div id="loading" style="display: none;">
            <div class="loader"></div>
        </div>
    </main>

    <script>
        let jsonData = <?= json_encode($dataMisiones['data']) ?>;

        function updateCheckboxes(selectedNombre) {
            const checkboxSection = document.getElementById('checkbox-section');
            const checkboxContainer = document.getElementById('checkbox-container');

            checkboxContainer.innerHTML = '';

            if (selectedNombre) {
                const opciones = jsonData
                    .filter(item => item.columna_1 === selectedNombre)
                    .map(item => item.columna_4)
                    .filter(opcion => opcion && opcion.trim() !== '');

                opciones.unshift("-------Comentario, ranqueo-------");

                const opcionesUnicas = [...new Set(opciones)];

                if (opcionesUnicas.length > 0) {
                    checkboxSection.style.display = 'block';

                    opcionesUnicas.forEach((opcion, index) => {
                        const checkboxId = `opcion-${index}`;

                        const div = document.createElement('div');
                        div.className = 'checkbox-option';

                        const input = document.createElement('input');
                        input.type = 'checkbox';
                        input.id = checkboxId;
                        input.name = 'opciones[]';
                        input.value = opcion;

                        const label = document.createElement('label');
                        label.htmlFor = checkboxId;

                        if (opcion && opcion.includes('https')) {
                            const link = document.createElement('a');
                            link.href = opcion;
                            link.textContent = opcion;
                            link.target = '_blank';
                            label.appendChild(link);
                        } else {
                            label.textContent = opcion;
                        }

                        div.appendChild(input);
                        div.appendChild(label);
                        checkboxContainer.appendChild(div);
                    });
                } else {
                    checkboxSection.style.display = 'none';
                }
            } else {
                checkboxSection.style.display = 'none';
            }
        }

        function handleSubmit(form) {
            const submitButton = document.getElementById('submitButton');
            submitButton.disabled = true;
            submitButton.textContent = 'Registrando...';

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form)
            })
            .then(response => response.text())
            .then(html => {
                const alertMatch = html.match(/alert\('([^']+)'\)/);
                if (alertMatch) {
                    alert(alertMatch[1]);
                    window.location.reload();
                } else {
                    alert('Error al procesar la respuesta');
                    submitButton.disabled = false;
                    submitButton.textContent = 'Registrar';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al procesar la solicitud');
                submitButton.disabled = false;
                submitButton.textContent = 'Registrar';
            });

            return false;
        }

        function toggleSearch(event) {
            const search = document.getElementById('nombreSearch');
            if (search.style.display === 'none' || !search.style.display) {
                search.style.display = 'block';
                search.querySelector('input').focus();
            } else {
                search.style.display = 'none';
            }
        }

        function filterNombres() {
            const input = document.querySelector('.select-search input');
            const filter = input.value.toLowerCase();
            const options = document.querySelectorAll('.select-search .option');

            options.forEach(option => {
                const txtValue = option.textContent || option.innerText;
                if (txtValue.toLowerCase().indexOf(filter) > -1) {
                    option.style.display = "";
                } else {
                    option.style.display = "none";
                }
            });
        }

        function selectOption(element) {
            const value = element.getAttribute('data-value');
            const select = document.getElementById('nombre');
            select.value = value;
            updateCheckboxes(value);
            document.getElementById('nombreSearch').style.display = 'none';
        }

        document.addEventListener('click', function(event) {
            const search = document.getElementById('nombreSearch');
            const select = document.getElementById('nombre');
            if (!search.contains(event.target) && event.target !== select) {
                search.style.display = 'none';
            }
        });

        function fetchNombresPorActor(actor) {
            if (!actor) {
                const select = document.getElementById('nombre');
                select.innerHTML = '<option value="">Seleccione un nombre</option>';
                updateCheckboxes('');
                jsonData = [];
                const checkboxSection = document.getElementById('checkbox-section');
                const checkboxContainer = document.getElementById('checkbox-container');
                checkboxContainer.innerHTML = '';
                checkboxSection.style.display = 'none';
                document.querySelector('#nombreSearch .options').innerHTML = '';
                return;
            }
            fetch('get_nombres.php?actor=' + encodeURIComponent(actor))
                .then(response => response.json())
                .then(data => {
                    jsonData = Array.isArray(data.data) ? data.data : [];
                    const select = document.getElementById('nombre');
                    select.innerHTML = '<option value="">Seleccione un nombre</option>';
                    const nombresUnicos = [...new Set(jsonData.map(item => item.columna_1))];
                    nombresUnicos.forEach(nombre => {
                        const option = document.createElement('option');
                        option.value = nombre;
                        option.textContent = nombre;
                        select.appendChild(option);
                    });
                    updateCheckboxes('');
                    const checkboxSection = document.getElementById('checkbox-section');
                    const checkboxContainer = document.getElementById('checkbox-container');
                    checkboxContainer.innerHTML = '';
                    checkboxSection.style.display = 'none';
                    const optionsDiv = document.querySelector('#nombreSearch .options');
                    optionsDiv.innerHTML = '';
                    nombresUnicos.forEach(nombre => {
                        const div = document.createElement('div');
                        div.className = 'option';
                        div.setAttribute('data-value', nombre);
                        div.onclick = function() { selectOption(this); };
                        div.textContent = nombre;
                        optionsDiv.appendChild(div);
                    });
                })
                .catch(error => {});
        }

        document.getElementById('nombre').addEventListener('change', function() {
            updateCheckboxes(this.value);
        });
    </script>
</body>
</html>
