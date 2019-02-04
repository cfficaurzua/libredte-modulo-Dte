<ul class="nav nav-pills float-right">
    <li class="nav-item">
        <a href="<?=$_base?>/dte/dte_guias" title="Ir al libro de guías de despacho" class="nav-link">
            <i class="fa fa-book"></i>
            Libro de guías
        </a>
    </li>
</ul>
<div class="page-header"><h1>Facturación masiva de guías de despacho</h1></div>
<?php
$f = new \sowerphp\general\View_Helper_Form();
echo $f->begin(['id'=>'buscarForm', 'onsubmit'=>'Form.check(\'buscarForm\')']);
echo $f->input([
    'type' => 'date',
    'name' => 'desde',
    'label' => 'Desde',
    'value' => !empty($_POST['desde']) ? $_POST['desde'] : date('Y-m-01'),
    'check' => 'notempty date',
]);
echo $f->input([
    'type' => 'date',
    'name' => 'hasta',
    'label' => 'Hasta',
    'value' => !empty($_POST['hasta']) ? $_POST['hasta'] : date('Y-m-d'),
    'check' => 'notempty date',
]);
echo $f->input([
    'name' => 'receptor',
    'label' => 'Receptor',
    'check' => 'rut',
]);
echo $f->input([
    'type' => 'select',
    'name' => 'con_referencia',
    'label' => '¿Con referencia?',
    'options' => ['Sólo guías sin referencia (sin facturar)', 'Incluir guías que tengan una referencia (podrían estar facturadas)'],
]);
echo $f->end('Buscar guías a facturar');
// mostrar guías
if (isset($guias)) {
    echo '<hr/>';
    echo '<p>Se encontraron las siguientes guías de despacho sin facturar:</p>';
    foreach ($guias as &$g) {
        $g['total'] = num($g['total']);
        $acciones = '<a href="'.$_base.'/dte/dte_emitidos/ver/52/'.$g['folio'].'" class="btn btn-primary"><i class="fa fa-search fa-fw"></i></a>';
        $acciones .= ' <a href="'.$_base.'/dte/dte_emitidos/pdf/52/'.$g['folio'].'" class="btn btn-primary"><i class="far fa-file-pdf fa-fw"></i></a>';
        $g[] = $acciones;
    }
    echo $f->begin(['id'=>'facturarForm', 'onsubmit'=>'Form.check(\'facturarForm\')']);
    echo $f->input([
        'type'=>'tablecheck',
        'name'=>'guias',
        'label'=>'Guías',
        'titles'=>['Guía', 'Receptor', 'Fecha', 'Total', 'Acciones'],
        'table'=>$guias,
    ]);
    echo $f->input([
        'type' => 'date',
        'name' => 'fecha',
        'label' => 'Fecha',
        'value' => !empty($_POST['fecha']) ? $_POST['fecha'] : date('Y-m-d'),
        'check' => 'notempty date',
        'help' => 'Fecha de emisión de la factura',
    ]);
    echo $f->end('Facturar guías seleccionadas');
}
// mostrar resultado de temporales creados
if (isset($temporales)) {
    echo '<hr/>';
    echo '<p>Se generaron los siguientes documentos temporales:</p>';
    $tabla = [];
    foreach ($temporales as $DteTmp) {
        $acciones = '<a href="'.$_base.'/dte/dte_tmps/cotizacion/'.$DteTmp->receptor.'/'.$DteTmp->dte.'/'.$DteTmp->codigo.'" title="Descargar cotización" class="btn btn-primary"><i class="fas fa-dollar-sign fa-fw"></i></a>';
        $acciones .= ' <a href="'.$_base.'/dte/dte_tmps/pdf/'.$DteTmp->receptor.'/'.$DteTmp->dte.'/'.$DteTmp->codigo.'" title="Descargar previsualización" class="btn btn-primary"><i class="far fa-file-pdf fa-fw"></i></a>';
        $acciones .= ' <a href="'.$_base.'/dte/dte_tmps/ver/'.$DteTmp->receptor.'/'.$DteTmp->dte.'/'.$DteTmp->codigo.'" title="Ver el documento temporal" class="btn btn-primary"><i class="fa fa-search fa-fw"></i></a>';
        $acciones .= ' <a href="'.$_base.'/dte/documentos/generar/'.$DteTmp->receptor.'/'.$DteTmp->dte.'/'.$DteTmp->codigo.'" title="Generar DTE y enviar al SII" onclick="return Form.checkSend(\'¿Está seguro de querer generar el DTE?\')" class="btn btn-primary"><i class="far fa-paper-plane fa-fw"></i></a>';
        $tabla[] = [
            $DteTmp->getFolio(),
            $DteTmp->getReceptor()->razon_social,
            num($DteTmp->total),
            $acciones
        ];
    }
    array_unshift($tabla, ['Folio', 'Receptor', 'Total', 'Acciones']);
    $t = new \sowerphp\general\View_Helper_Table();
    $t->setColsWidth([null, null, null, 200]);
    echo $t->generate($tabla);
}
