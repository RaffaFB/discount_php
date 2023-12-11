<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Capturar os dados do formulário
    $parc = $_POST["np"];
    $itax = $_POST["tax"];
    $ipv = $_POST["pv"];
    $ipp = $_POST["pp"];
    $ipb = $_POST["pb"];
    $mtb = $_POST["mtb"];
    $idp = isset($_POST["dp"]) ? $_POST["dp"] : 0;
    $entry = $_POST["entry"];

    // Funções de validação e processamento
    validarFormulario();
}

// Defina as variáveis globais, se necessário
$mostrarElementos = false;

function exibirMensagemErro($errorMessage) {
    echo '<div id="errorMessage" class="messages" style="color: red; font-size: 90% !important; display: block; margin-top: 20px;">';
    echo $errorMessage;
    echo '</div>';
}

function exibirMensagemSucesso() {
    // Lógica para exibir mensagem de sucesso, se necessário
}

function validarFormulario() {
    $errorMessage = "";
    $monthlyRate = isset($_POST["itax"]) ? floatval($_POST["itax"]) : 0;
    $principalValue = isset($_POST["ipv"]) ? floatval($_POST["ipv"]) : 0;
    $finalValue = isset($_POST["ipp"]) ? floatval($_POST["ipp"]) : 0;
    $time = isset($_POST["parc"]) ? floatval($_POST["parc"]) : 0;
    $entryValue = isset($_POST["financingEntry"]) ? floatval($_POST["financingEntry"]) : 0;
    $monthsToBack = isset($_POST["mtb"]) ? floatval($_POST["mtb"]) : 0;
    $valueToBack = isset($_POST["ipb"]) ? floatval($_POST["ipb"]) : 0;

    if ($monthlyRate === 0 && $principalValue === 0) {
        $errorMessage .= "<p>Taxa de juros e valor financiado não podem ser ambos nulos.</p>";
    }
    if ($monthlyRate === 0 && $finalValue === 0) {
        $errorMessage .= "<p>Taxa de juros e valor final não podem ser ambos nulos.</p>";
    }
    if ($finalValue === 0 && $principalValue === 0) {
        $errorMessage .= "<p>Valor financiado e valor final não podem ser ambos nulos.</p>";
    }

    if (!empty($errorMessage)) {
        exibirMensagemErro($errorMessage);
    } else {
        // Aqui você pode chamar uma função PHP para validar os dados ou realizar outras ações necessárias.
        exibirMensagemSucesso();
    }
}

function validarDados($principalValue, $finalValue, $time, $monthlyRate, $checkbox, $entryValue, $monthsToBack, $valueToBack) {
    // Funções correspondentes aos cálculos JavaScript
    $backedValue = calcularRetorno($monthsToBack, $valueToBack, $monthsToBack);

    if ($monthlyRate === 0.0) {
        $monthlyRate = calcularTaxa($principalValue, $finalValue, $time, $checkbox);
        $installmentValue = calcularValorInstallment($time, $principalValue, $monthlyRate, $entryValue, $finalValue);
        $table = tabelaPrice($principalValue, $time, $monthlyRate, $entryValue, $installmentValue);
        popularTabela($table, $finalValue, $principalValue, $entryValue);
        $coefficient = calculatefinancingCoefficient($monthlyRate, $time);
        mostrarDados($monthlyRate, $principalValue, $finalValue, $time, $installmentValue, $checkbox, $coefficient, $valueToBack, $monthsToBack, $backedValue, $entryValue);

    } elseif ($finalValue === 0.0) {
        $installmentValue = calcularValorInstallment($time, $principalValue, $monthlyRate, $entryValue, $finalValue);
        $finalValue = calcularValorFinal($installmentValue, $time, $entryValue);
        $table = tabelaPrice($principalValue, $time, $monthlyRate, $entryValue, $installmentValue);
        popularTabela($table, $finalValue, $principalValue, $entryValue);
        $coefficient = calculatefinancingCoefficient($monthlyRate, $time);
        mostrarDados($monthlyRate, $principalValue, $finalValue, $time, $installmentValue, $checkbox, $coefficient, $valueToBack, $monthsToBack, $backedValue, $entryValue);

    } elseif ($principalValue === 0.0) {
        $installmentValue = calcularValorInstallment($time, $principalValue, $monthlyRate, $entryValue, $finalValue);
        $principalValue = calculatePrincipalValue($monthlyRate, $time, $installmentValue);
        $table = tabelaPrice($principalValue, $time, $monthlyRate, $entryValue, $installmentValue);
        popularTabela($table, $finalValue, $principalValue, $entryValue);
        $coefficient = calculatefinancingCoefficient($monthlyRate, $time);
        mostrarDados($monthlyRate, $principalValue, $finalValue, $time, $installmentValue, $checkbox, $coefficient, $valueToBack, $monthsToBack, $backedValue, $entryValue);
    }
}

function calcularEquacao($principalValue, $finalValue, $interestRate, $checkbox, $time) {
    $a = 0;
    $b = 0;
    $c = 0;

    if ($checkbox) {
        // $a = pow(1 + $interestRate, $time - 2);
        $b = pow(1 + $interestRate, $time - 1);
        $c = pow(1 + $interestRate, $time);

        return ($principalValue + $interestRate * $b) - ($finalValue / $time * ($c - 1));
    } else {
        $a = pow(1 + $interestRate, -$time);
        $b = pow(1 + $interestRate, -$time - 1);

        return ($principalValue * $interestRate) - (($finalValue / $time) * (1 - $a));
    }
}

function calcularDerivada($principalValue, $finalValue, $interestRate, $checkbox, $time) {
    $a = 0;
    $b = 0;
    $c = 0;

    if ($checkbox) {
        $a = pow(1 + $interestRate, $time - 2);
        $b = pow(1 + $interestRate, $time - 1);

        return $principalValue * ($b + ($interestRate * $a * ($time - 1))) - ($finalValue * $b);
    } else {
        $a = pow(1 + $interestRate, -$time);
        $b = pow(1 + $interestRate, -$time - 1);

        return $principalValue - ($finalValue * $b);
    }
}

function calcularTaxa($principalValue, $finalValue, $time, $checkbox) {
    $tolerance = 0.0001;
    $interestRate = 0.1; // initial guess
    $interestRateBefore = 0.0;

    $equation = 0;
    $derivative = 0;

    while (abs($interestRateBefore - $interestRate) >= $tolerance) {
        $interestRateBefore = $interestRate;
        $equation = calcularEquacao($principalValue, $finalValue, $interestRate, $checkbox, $time);
        $derivative = calcularDerivada($principalValue, $finalValue, $interestRate, $checkbox, $time);

        $interestRate = $interestRate - ($equation / $derivative);
    }

    return $interestRate * 100;
}

function calcularValorFinal($installmentValue, $numberOfInstallment, $entryValue) {
    return ($installmentValue * $numberOfInstallment) + $entryValue;
}

function calcularValorInstallment($time, $principalValue, $monthlyRate, $entryValue, $finalValue) {
    if ($principalValue == 0.0) {
        return $finalValue / $time;
    }

    $fator = pow(1 + $monthlyRate / 100, $time);
    $installmentValue = (($principalValue - $entryValue) * ($monthlyRate / 100) * $fator) / ($fator - 1);

    return $installmentValue;
}

function tabelaPrice($principalValue, $time, $monthlyRate, $entryValue, $installmentValue) {
    $tabelaPrice = [];
    $outstandingBalance = $principalValue - $entryValue;

    for ($i = 1; $i <= $time; $i++) {
        $interestValue = $outstandingBalance * ($monthlyRate / 100);
        $amortization = $installmentValue - $interestValue;
        $outstandingBalance -= $amortization;

        $tabelaPrice[] = [
            'installmentNumber' => $i,
            'installmentValue' => $installmentValue,
            'interestValue' => $interestValue,
            'amortization' => $amortization,
            'outstandingBalance' => $outstandingBalance,
        ];
    }

    return $tabelaPrice;
}

function popularTabela($tableData, $finalValue, $principalValue, $entryValue) {
    echo '<table id="price-table">';

    // Header row
    echo '<tr>';
    echo '<th>Index</th>';
    echo '<th>Installment Value</th>';
    echo '<th>Interest Rate Value</th>';
    echo '<th>Amortization Value</th>';
    echo '<th>Outstanding Balance</th>';
    echo '</tr>';

    // Data rows
    foreach ($tableData as $item) {
        echo '<tr>';
        echo '<td>' . $item['installmentNumber'] . '</td>';
        echo '<td>R$ ' . number_format($item['installmentValue'], 2) . '</td>';
        echo '<td>R$ ' . number_format($item['interestValue'], 2) . '</td>';
        echo '<td>R$ ' . number_format($item['amortization'], 2) . '</td>';
        echo '<td>R$ ' . number_format($item['outstandingBalance'], 2) . '</td>';
        echo '</tr>';
    }

    // Final row
    echo '<tr>';
    echo '<td>Total: </td>';
    echo '<td>R$ ' . number_format($finalValue, 2) . '</td>';
    echo '<td>R$ ' . number_format(($finalValue - $principalValue), 2) . '</td>';
    echo '<td>R$ ' . number_format(($principalValue - $entryValue), 2) . '</td>';
    echo '<td>0</td>';
    echo '</tr>';

    echo '</table>';
}

function mostrarDados($monthlyRate, $principalValue, $finalValue, $time, $installmentValue, $checkbox, $FCoefficient, $valueToBack, $monthsToBack, $backedValue, $entryValue) {
    $importantDataHTML1 =
        '<p class="title">Important data</p>' .
        '<p>Parcelamento: ' . $time . ' meses </p>' .
        '<p>Taxa: ' . number_format($monthlyRate, 2) . ' % a.m</p>' .
        '<p>Coeficiente de financiamento: ' . number_format($FCoefficient, 2) . '</p>' .
        '<p>Prestação: R$ ' . number_format($installmentValue, 2) . ' </p>';

    $importantDataHTML2 =
        '<p class="title">Important data</p>' .
        '<p>Valor Financiado: R$ ' . number_format($principalValue, 2) . ' </p>' .
        '<p>Valor Final: R$ ' . number_format($finalValue, 2) . ' </p>' .
        '<p>Entrada: ' . ($checkbox ? 'Sim' : 'Não') . '</p>' .
        '<p>Valor da Entrada: R$ ' . number_format($entryValue, 2) . '</p>';

    $importantDataHTML3 =
        '<p class="title">Important data</p>' .
        '<p>Valor a Voltar: R$ ' . number_format($valueToBack, 2) . ' </p>' .
        '<p>Meses a Voltar: ' . $monthsToBack . ' </p>' .
        '<p>Valor Presente: R$ ' . number_format($backedValue, 2) . '</p>';

    // Enviar os dados para o navegador como parte do HTML gerado pelo PHP
    echo '<script>';
    echo 'var importantDataHTML1 = ' . json_encode($importantDataHTML1) . ';';
    echo 'var importantDataHTML2 = ' . json_encode($importantDataHTML2) . ';';
    echo 'var importantDataHTML3 = ' . json_encode($importantDataHTML3) . ';';
    echo '</script>';
}

// Função para calcular o coeficiente de financiamento
function calculatefinancingCoefficient($monthlyRate, $time) {
    $fator = pow(1 + $monthlyRate / 100, -$time);
    return ($monthlyRate / 100) / (1 - $fator);
}

// Função para exibir ou ocultar entryDIV com base na condição do checkbox
function displayEntryDIV($checkboxChecked) {
    if ($checkboxChecked) {
        echo '<style>#entryDIV { display: block; }</style>';
    } else {
        echo '<style>#entryDIV { display: none; }</style>';
    }
}

// Função para calcular o retorno
function calcularRetorno($monthsToBack, $valueToBack, $monthlyRate) {
    $fator = pow(1 + $monthlyRate / 100, $monthsToBack);
    return $valueToBack / $fator;
}

// Função para calcular o valor principal
function calculatePrincipalValue($monthlyRate, $time, $installmentValue) {
    $fator = pow(1 + $monthlyRate / 100, -$time);
    $principalValue = $installmentValue * ((1 - $fator) / ($monthlyRate / 100));
    return $principalValue;
}

echo '<script>';
echo 'document.getElementById("submitButton").addEventListener("click", validador);';
echo 'document.getElementById("idp").addEventListener("change", displayEntryDIV);';
echo 'document.getElementById("myButton").addEventListener("click", function(){ window.open("index.html", "_self"); });';
echo '</script>';

?>
<!-- Agora, adicione o código JavaScript gerado pelo PHP -->
<script>
    var importantDataHTML1 = <?php echo json_encode($importantDataHTML1); ?>;
    var importantDataHTML2 = <?php echo json_encode($importantDataHTML2); ?>;
    var importantDataHTML3 = <?php echo json_encode($importantDataHTML3); ?>;

    // Agora, você pode usar essas variáveis no seu código JavaScript.
</script>
