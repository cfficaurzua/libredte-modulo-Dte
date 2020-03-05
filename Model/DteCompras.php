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
namespace website\Dte;

/**
 * Clase para mapear la tabla dte_compra de la base de datos
 * Comentario de la tabla:
 * Esta clase permite trabajar sobre un conjunto de registros de la tabla dte_compra
 * @author SowerPHP Code Generator
 * @version 2015-09-28 01:07:23
 */
class Model_DteCompras extends \Model_Plural_App
{

    // Datos para la conexión a la base de datos
    protected $_database = 'default'; ///< Base de datos del modelo
    protected $_table = 'dte_compra'; ///< Tabla del modelo

    /**
     * Método que indica si el libro para cierto periodo está o no generado
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2018-06-14
     */
    public function libroGenerado($periodo)
    {
        return $this->db->getValue('
            SELECT COUNT(*)
            FROM dte_compra
            WHERE receptor = :receptor AND periodo = :periodo AND certificacion = :certificacion AND track_id IS NOT NULL
        ', [':receptor'=>$this->getContribuyente()->rut, ':periodo'=>$periodo, ':certificacion'=>$this->getContribuyente()->config_ambiente_en_certificacion]);
    }

    /**
     * Método que entrega el total mensual del libro de compras
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2020-02-20
     */
    public function getTotalesMensuales($anio)
    {
        $periodo_actual = date('Ym');
        $periodo = $anio.'01';
        $totales_mensuales = [];
        for ($i=0; $i<12; $i++) {
            if ($periodo>$periodo_actual) {
                break;
            }
            $totales_mensuales[$periodo] = array_merge(
                ['periodo'=>$periodo],
                (new Model_DteCompra($this->getContribuyente()->rut, $periodo, $this->getContribuyente()->config_ambiente_en_certificacion))->getTotales()
            );
            $periodo = \sowerphp\general\Utility_Date::nextPeriod($periodo);
        }
        return $totales_mensuales;
    }

    /**
     * Método que entrega el resumen anual de compras
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2020-02-20
     */
    public function getResumenAnual($anio)
    {
        $libros = [];
        foreach (range(1,12) as $mes) {
            $mes = $mes < 10 ? '0'.$mes : $mes;
            $DteCompra = new Model_DteCompra($this->getContribuyente()->rut, (int)($anio.$mes), (int)$this->getContribuyente()->config_ambiente_en_certificacion);
            $resumen = $DteCompra->getResumen();
            if ($resumen) {
                $libros[$anio][$mes] = $resumen;
            }
        }
        // ir sumando en el resumen anual
        $resumen = [];
        if (!empty($libros[$anio])) {
            foreach($libros[$anio] as $mes => $resumen_mensual) {
                foreach ($resumen_mensual as $r) {
                    $cols = array_keys($r);
                    unset($cols[array_search('TpoDoc',$cols)]);
                    if (!isset($resumen[$r['TpoDoc']])) {
                        $resumen[$r['TpoDoc']] = ['TpoDoc' => $r['TpoDoc']];
                        foreach ($cols as $col) {
                            $resumen[$r['TpoDoc']][$col] = 0;
                        }
                    }
                    foreach ($cols as $col) {
                        $resumen[$r['TpoDoc']][$col] += (float)$r[$col];
                    }
                }
            }
        }
        ksort($resumen);
        return $resumen;
    }

    /**
     * Método que entrega el resumen de los documentos de compras
     * totalizado según ciertos filtros y por tipo de documento.
     * @todo Agregar las facturas de compras al resumen (tipo 46)
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2020-03-05
     */
    public function getResumen(array $filtros = [])
    {
        $where = ['d.receptor = :receptor', 'd.certificacion = :certificacion'];
        $vars = [
            ':receptor' => $this->getContribuyente()->rut,
            ':certificacion' => (int)$this->getContribuyente()->config_ambiente_en_certificacion,
        ];
        // filtrar por tipo de DTE
        if (!empty($filtros['dte'])) {
            if (!empty($filtros['dte'])) {
                if (is_array($filtros['dte'])) {
                    $i = 0;
                    $where_dte = [];
                    foreach ($filtros['dte'] as $filtro_dte) {
                        $where_dte[] = ':dte'.$i;
                        $vars[':dte'.$i] = $filtro_dte;
                        $i++;
                    }
                    $where[] = 'd.dte IN ('.implode(', ', $where_dte).')';
                }
                else if ($filtros['dte'][0]=='!') {
                    $where[] = 'd.dte != :dte';
                    $vars[':dte'] = substr($filtros['dte'],1);
                }
                else {
                    $where[] = 'd.dte = :dte';
                    $vars[':dte'] = $filtros['dte'];
                }
            }
        } else {
            $where[] = 't.compra = true';
        }
        // otros filtros
        if (!empty($filtros['fecha_desde'])) {
            $where[] = 'd.fecha >= :fecha_desde';
            $vars[':fecha_desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[] = 'd.fecha <= :fecha_hasta';
            $vars[':fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (!empty($filtros['periodo'])) {
            $periodo_col = $this->db->date('Ym', 'd.fecha');
            $where[] = '(d.periodo IS NOT NULL AND d.periodo = :periodo) OR (d.periodo IS NULL AND '. $periodo_col.'::INTEGER = :periodo)';
            $vars[':periodo'] = $filtros['periodo'];
        }
        if (!empty($filtros['usuario'])) {
            if (is_numeric($filtros['usuario'])) {
                $where[] = 'u.id = :usuario';
            } else {
                $where[] = 'u.usuario = :usuario';
            }
            $vars[':usuario'] = $filtros['usuario'];
        }
        // generar consulta
        return $this->db->getTable('
            SELECT
                t.codigo,
                t.tipo,
                t.operacion,
                COUNT(d.dte) AS documentos,
                SUM(d.exento) AS exento,
                SUM(d.neto) AS neto,
                SUM(d.iva) AS iva,
                SUM(d.total) AS total
            FROM
                dte_recibido AS d
                JOIN usuario AS u ON u.id = d.usuario
                JOIN dte_tipo AS t ON t.codigo = d.dte
            WHERE '.implode(' AND ', $where).'
            GROUP BY t.codigo, t.tipo, t.operacion
            ORDER BY t.operacion DESC, t.tipo ASC
        ', $vars);
    }

    /**
     * Método que sincroniza el libro de compras local con el registro de compras del SII
     * - Se agregan documentos "registrados" en el registro de compras del SII
     * - Se eliminan documentos que están en el SII marcados como "no incluir" o "reclamados"
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2020-02-19
     */
    public function sincronizarRegistroComprasSII($meses = 2)
    {
        $documentos_encontrados = 0;
        // periodos a procesar
        $periodo_actual = (int)date('Ym');
        $periodos = [$periodo_actual];
        for ($i = 0; $i < $meses-1; $i++) {
            $periodos[] = \sowerphp\general\Utility_Date::previousPeriod($periodos[$i]);
        }
        sort($periodos);
        // sincronizar periodos
        foreach ($periodos as $periodo) {
            $config = ['periodo'=>$periodo];
            $documentos = $this->getContribuyente()->getRCV([
                'operacion' => 'COMPRA',
                'periodo' => $periodo,
                'estado' => 'REGISTRO',
                'tipo' => 'iecv'
            ]);
            $documentos_encontrados += count($documentos);
            $this->agregarMasivo($documentos, $config);
            $this->eliminarMasivo(
                $this->getContribuyente()->getRCV([
                    'operacion' => 'COMPRA',
                    'periodo' => $periodo,
                    'estado' => 'NO_INCLUIR',
                    'tipo' => 'iecv'
                ])
            );
            $this->eliminarMasivo(
                $this->getContribuyente()->getRCV([
                    'operacion' => 'COMPRA',
                    'periodo' => $periodo,
                    'estado' => 'RECLAMADO',
                    'tipo' => 'iecv'
                ])
            );
        }
        return $documentos_encontrados;
    }

    /**
     * Método que agrega masivamente documentos recibidos y acepta los intercambios asociados al DTE
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2020-02-19
     */
    private function agregarMasivo($documentos, array $config = [])
    {
        $config = array_merge([
            'periodo' => (int)date('Ym'),
            'sucursal' => 0,
        ], $config);
        $Emisores = new Model_Contribuyentes();
        $DteIntercambios = (new Model_DteIntercambios())->setContribuyente($this->getContribuyente());
        foreach ($documentos as $doc) {
            // si el documento está anulado se omite
            if ($doc['anulado']) {
                continue;
            }
            // aceptar intercambios
            $intercambios = $DteIntercambios->buscarIntercambiosDte(substr($doc['rut'],0,-2), $doc['dte'], $doc['folio']);
            if ($intercambios) {
                foreach ($intercambios as $DteIntercambio) {
                    if (!$DteIntercambio->usuario and $DteIntercambio->documentos == 1) {
                        $DteIntercambio->responder(true, $config);
                    }
                }
            }
            // agregar el documento recibido si no existe
            $Emisor = $Emisores->get(substr($doc['rut'],0,-2));
            $DteRecibido = new Model_DteRecibido($Emisor->rut, $doc['dte'], $doc['folio'], (int)$this->getContribuyente()->config_ambiente_en_certificacion);
            if (!$DteRecibido->usuario or $DteRecibido->mipyme) {
                $DteRecibido->tasa = (float)$doc['tasa'];
                $DteRecibido->fecha = $doc['fecha'];
                $DteRecibido->sucursal_sii = $doc['sucursal_sii'];
                $DteRecibido->exento = $doc['exento'];
                $DteRecibido->neto = $doc['neto'];
                $DteRecibido->iva = $doc['iva'] ? $doc['iva'] : 0;
                $DteRecibido->total = $doc['total'] ? $doc['total'] : 0;
                $DteRecibido->iva_uso_comun = $doc['iva_uso_comun'];
                $DteRecibido->iva_no_recuperable =
                    $doc['iva_no_recuperable_monto']
                    ? json_encode([['codigo'=>$doc['iva_no_recuperable_codigo'], 'monto'=>$doc['iva_no_recuperable_monto']]])
                    : null
                ;
                $DteRecibido->impuesto_adicional = null;
                $DteRecibido->impuesto_tipo = $doc['impuesto_tipo'];
                $DteRecibido->impuesto_sin_credito = $doc['impuesto_sin_credito'];
                $DteRecibido->monto_activo_fijo = $doc['monto_activo_fijo'];
                $DteRecibido->monto_iva_activo_fijo = $doc['monto_iva_activo_fijo'];
                $DteRecibido->iva_no_retenido = $doc['iva_no_retenido'];
                $DteRecibido->periodo = $config['periodo'];
                $DteRecibido->impuesto_puros = $doc['impuesto_puros'];
                $DteRecibido->impuesto_cigarrillos = $doc['impuesto_cigarrillos'];
                $DteRecibido->impuesto_tabaco_elaborado = $doc['impuesto_tabaco_elaborado'];
                $DteRecibido->impuesto_vehiculos = $doc['impuesto_vehiculos'];
                $DteRecibido->numero_interno = $doc['numero_interno'];
                $DteRecibido->emisor_nc_nd_fc = $doc['emisor_nc_nd_fc'];
                $DteRecibido->sucursal_sii_receptor = $config['sucursal'];
                $DteRecibido->rcv_accion = null;
                $DteRecibido->tipo_transaccion = null;
                $DteRecibido->receptor = $this->getContribuyente()->rut;
                $DteRecibido->usuario = $this->getContribuyente()->getUsuario()->id;
                $DteRecibido->save();
            }
            // si el documento existe
            else {
                // corregir periodo si tenía uno incorrecto
                if ($DteRecibido->periodo != $config['periodo']) {
                    $DteRecibido->periodo = $config['periodo'];
                    $DteRecibido->save();
                }
            }
        }
    }

    /**
     * Método que elimina masivamente documentos recibidos y los intercambios asociados al DTE
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2018-05-20
     */
    private function eliminarMasivo($documentos)
    {
        $DteIntercambios = (new Model_DteIntercambios())->setContribuyente($this->getContribuyente());
        foreach ($documentos as $doc) {
            // eliminar DTE recibido
            $DteRecibido = new Model_DteRecibido();
            $DteRecibido->emisor = substr($doc['rut'],0,-2);
            $DteRecibido->dte = $doc['dte'];
            $DteRecibido->folio = $doc['folio'];
            $DteRecibido->certificacion = (int)$this->getContribuyente()->config_ambiente_en_certificacion;
            $DteRecibido->delete();
            // eliminar intercambio
            $intercambios = $DteIntercambios->buscarIntercambiosDte(substr($doc['rut'],0,-2), $doc['dte'], $doc['folio']);
            if ($intercambios) {
                foreach ($intercambios as $DteIntercambio) {
                    if ($DteIntercambio->documentos == 1) {
                        $DteIntercambio->delete();
                    }
                }
            }
        }
    }

    /**
     * Método que sincroniza los documentos recibidos del Portal MIPYME con
     * LibreDTE, cargando los datos que estén en el SII
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2020-02-22
     */
    public function sincronizarRecibidosPortalMipymeSII($meses = 2)
    {
        $documentos_encontrados = 0;
        // periodos a procesar
        $periodo_actual = (int)date('Ym');
        $periodos = [$periodo_actual];
        for ($i = 0; $i < $meses-1; $i++) {
            $periodos[] = \sowerphp\general\Utility_Date::previousPeriod($periodos[$i]);
        }
        sort($periodos);
        // sincronizar periodos
        foreach ($periodos as $periodo) {
            // obtener documentos recibidos en el portal mipyme
            $r = libredte_api_consume(
                '/sii/mipyme/recibidos/documentos/'.$this->getContribuyente()->getRUT().'?formato=json',
                [
                    'auth' => $this->getContribuyente()->getSiiAuthUser(),
                    'filtros' => [
                        'FEC_DESDE' => \sowerphp\general\Utility_Date::normalize($periodo.'01'),
                        'FEC_HASTA' => \sowerphp\general\Utility_Date::lastDayPeriod($periodo),
                    ],
                ]
            );
            if ($r['status']['code'] != 200) {
                throw new \Exception('Error al sincronizar recibidos del período '.$periodo.': '.$r['body'], $r['status']['code']);
            }
            // guardar documentos encontrados
            $Emisores = new Model_Contribuyentes();
            $documentos = (array)$r['body'];
            $documentos_encontrados += count($documentos);
            foreach($documentos as $dte) {
                $Emisor = $Emisores->get($dte['rut']);
                $DteRecibido = new Model_DteRecibido($Emisor->rut, $dte['dte'], $dte['folio'], 0);
                if ($DteRecibido->mipyme) {
                    continue;
                }
                $DteRecibido->receptor = $this->getContribuyente()->rut;
                $DteRecibido->fecha = $dte['fecha'];
                $DteRecibido->total = $dte['total'];
                $DteRecibido->mipyme = $dte['codigo'];
                $DteRecibido->usuario = $this->getContribuyente()->usuario;
                $DteRecibido->save();
            }
        }
        return $documentos_encontrados;
    }

}
