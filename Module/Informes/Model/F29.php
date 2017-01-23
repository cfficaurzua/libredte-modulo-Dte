<?php

/**
 * LibreDTE
 * Copyright (C) SASCO SpA (https://sasco.cl)
 *
 * Este programa es software libre: usted puede redistribuirlo y/o
 * modificarlo bajo los términos de la Licencia Pública General Affero de GNU
 * publicada por la Fundación para el Software Libre, ya sea la versión
 * 3 de la Licencia, o (a su elección) cualquier versión posterior de la
 * misma.
 *
 * Este programa se distribuye con la esperanza de que sea útil, pero
 * SIN GARANTÍA ALGUNA; ni siquiera la garantía implícita
 * MERCANTIL o de APTITUD PARA UN PROPÓSITO DETERMINADO.
 * Consulte los detalles de la Licencia Pública General Affero de GNU para
 * obtener una información más detallada.
 *
 * Debería haber recibido una copia de la Licencia Pública General Affero de GNU
 * junto a este programa.
 * En caso contrario, consulte <http://www.gnu.org/licenses/agpl.html>.
 */

// namespace del modelo
namespace website\Dte\Informes;

/**
 * Modelo para obtener los datos del formulrio 29
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
 * @version 2016-02-01
 */
class Model_F29
{

    private $datos; ///< Arreglo con código y valores del formulario 29

    /**
     * Constructor del modelo F29
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2017-01-23
     */
    public function __construct(\website\Dte\Model_Contribuyente $Emisor, $periodo)
    {
        $this->Emisor = $Emisor;
        $this->periodo = (int)$periodo;
        // si hay libro de ventas se sacan de ahí las boletas y pagos electrónicos
        $boletas = ['total'=>0, 'iva'=>0];
        $pagos_electronicos = ['total'=>0, 'iva'=>0];
        $DteVenta = new \website\Dte\Model_DteVenta($Emisor->rut, $periodo, (int)$Emisor->config_ambiente_en_certificacion);
        if ($DteVenta->exists()) {
            $Libro = new \sasco\LibreDTE\Sii\LibroCompraVenta();
            $Libro->loadXML(base64_decode($DteVenta->xml));
            // resumenes boletas electrónicas
            $resumenBoletas = $Libro->getResumenBoletas();
            if (isset($resumenBoletas[39])) {
                $boletas = [
                    'total' => $resumenBoletas[39]['TotDoc'] - $resumenBoletas[39]['TotAnulado'],
                    'iva' => $resumenBoletas[39]['TotMntIVA'],
                ];
            }
            // resumenes manuales (boletas y pagos electrónicos)
            $resumenManual = $Libro->getResumenManual();
            if (isset($resumenManual[35])) {
                $boletas['total'] += $resumenManual[35]['TotDoc'] - $resumenManual[35]['TotAnulado'];
                $boletas['iva'] += $resumenManual[35]['TotMntIVA'];
            }
            if (isset($resumenManual[48])) {
                $pagos_electronicos = [
                    'total' => $resumenManual[48]['TotDoc'] - $resumenManual[48]['TotAnulado'],
                    'iva' => $resumenManual[48]['TotMntIVA'],
                ];
            }
        }
        // asignar datos
        $this->datos = [
            '01' => $this->Emisor->razon_social,
            '03' => num($Emisor->rut).'-'.$Emisor->dv,
            '06' => $this->Emisor->direccion,
            '08' => $this->Emisor->getComuna()->comuna,
            '09' => $this->Emisor->telefono,
            '15' => substr($periodo, 4).'/'.substr($periodo, 0, 4),            
            '55' => $this->Emisor->email,
            '110' => $boletas['total'],
            '111' => $boletas['iva'],
            '115' => $this->Emisor->config_contabilidad_ppm / 100,
            '313' => $this->Emisor->config_extra_contador_rut,
            '314' => $this->Emisor->config_extra_representante_rut,
            '758' => $pagos_electronicos['total'],
            '759' => $pagos_electronicos['iva'],
        ];
        if (\sowerphp\core\Module::loaded('Lce')) {
            $this->datos['48'] = (new \website\Lce\Model_LceCuenta($this->Emisor->rut, $this->Emisor->config_contabilidad_f29_48))->getHaber($this->periodo);
            $this->datos['151'] = (new \website\Lce\Model_LceCuenta($this->Emisor->rut, $this->Emisor->config_contabilidad_f29_151))->getHaber($this->periodo);
        }
    }

    public function setCompras($compras)
    {
    }

    public function setVentas($ventas)
    {
    }

    /**
     * Método que entrega un arreglo con los códigos del F29 y sus valores
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2016-02-01
     */
    public function getDatos()
    {
        return $this->datos;
    }

}
