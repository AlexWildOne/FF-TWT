<?php
namespace RoutesPro\Forms;

if (!defined('ABSPATH')) exit;

/**
 * Constrói uma representação compacta e normalizada de uma submissão.
 *
 * A base de dados continua a guardar a resposta original. Esta camada serve
 * apenas o BO, o portal e as exportações, evitando uma coluna por produto.
 */
class SubmissionPresentation {
    public static function build(array $answers): array {
        $report = [
            'answers' => [],
            'files' => [],
            'product_questions' => [],
            'order_questions' => [],
            'comparison_questions' => [],
            'summary' => [
                'answers' => 0,
                'files' => 0,
                'product_rows' => 0,
                'zero_products' => 0,
                'positive_products' => 0,
                'changed_products' => 0,
                'orders' => 0,
                'order_boxes' => 0,
                'order_units' => 0,
                'comparisons' => 0,
                'comparisons_below_target' => 0,
            ],
            'preview' => '',
        ];

        foreach ($answers as $answer) {
            if (!is_array($answer)) continue;
            $key = sanitize_key((string)($answer['question_key'] ?? ''));
            $label = sanitize_text_field((string)($answer['question_label'] ?? $key));
            if ($label === '') $label = $key !== '' ? $key : 'Resposta';

            $questionType = sanitize_key((string)($answer['question_type'] ?? '')) ?: self::inferQuestionType($answer);
            $comparisons = self::comparisonRows($answer);
            if ($comparisons !== null) {
                foreach ($comparisons as $comparison) {
                    $report['summary']['comparisons']++;
                    if (($comparison['status'] ?? '') === 'below') $report['summary']['comparisons_below_target']++;
                }
                $report['comparison_questions'][] = [
                    'key' => $key,
                    'label' => $label,
                    'type' => $questionType ?: 'share_space_matrix',
                    'type_label' => self::questionTypeLabel($questionType ?: 'share_space_matrix'),
                    'rows' => $comparisons,
                ];
                continue;
            }

            $orders = self::orderRows($answer);
            if ($orders !== null) {
                $activeRows = [];
                foreach ($orders as $row) {
                    if (!self::orderRowHasValue($row)) continue;
                    $activeRows[] = $row;
                    $report['summary']['orders']++;
                    if (is_numeric($row['order_qty'] ?? null)) $report['summary']['order_boxes'] += (float)$row['order_qty'];
                    if (is_numeric($row['units_total'] ?? null)) $report['summary']['order_units'] += (float)$row['units_total'];
                }
                $report['order_questions'][] = [
                    'key' => $key,
                    'label' => $label,
                    'type' => $questionType ?: 'order_suggestion',
                    'type_label' => self::questionTypeLabel($questionType ?: 'order_suggestion'),
                    'rows' => $activeRows,
                    'categories' => self::groupRowsByCategory($activeRows),
                    'catalogue_rows' => count($orders),
                ];
                continue;
            }

            $products = self::productRows($answer);
            if ($products !== null) {
                $normalisedRows = [];
                foreach ($products as $row) {
                    $row = self::decorateProductRow($row);
                    $normalisedRows[] = $row;
                    $report['summary']['product_rows']++;
                    if ($row['status'] === 'zero') $report['summary']['zero_products']++;
                    elseif ($row['status'] === 'positive') $report['summary']['positive_products']++;
                    if (!empty($row['changed'])) $report['summary']['changed_products']++;
                }
                $report['product_questions'][] = [
                    'key' => $key,
                    'label' => $label,
                    'type' => $questionType ?: 'product_matrix',
                    'type_label' => self::questionTypeLabel($questionType ?: 'product_matrix'),
                    'rows' => $normalisedRows,
                    'categories' => self::groupProductRowsByCategory($normalisedRows),
                ];
                continue;
            }

            $raw = self::rawValue($answer);
            if (!self::hasValue($raw)) continue;

            $fileItems = self::fileItems($raw);
            $fileQuestion = in_array($questionType, ['image_upload','categorized_image_upload','file_upload'], true);
            if ($fileItems && ($fileQuestion || self::looksLikeFile($label, (string)($fileItems[0]['url'] ?? '')))) {
                $fileType = $questionType ?: 'file_upload';
                foreach ($fileItems as $fileItem) {
                    $url = (string)($fileItem['url'] ?? '');
                    if ($url === '') continue;
                    $itemLabel = $label;
                    $categoryLabel = sanitize_text_field((string)($fileItem['category_label'] ?? ''));
                    if ($categoryLabel !== '' && stripos($itemLabel, $categoryLabel) === false) $itemLabel .= ' · ' . $categoryLabel;
                    $report['files'][] = [
                        'key' => $key,
                        'label' => $itemLabel,
                        'type' => $fileType,
                        'type_label' => self::questionTypeLabel($fileType),
                        'url' => $url,
                        'name' => sanitize_file_name((string)($fileItem['name'] ?? '')),
                        'mime_type' => sanitize_text_field((string)($fileItem['type'] ?? '')),
                    ];
                    $report['summary']['files']++;
                }
                continue;
            }

            $value = self::valueToText($raw);
            if ($value === '' && $value !== '0') continue;
            $report['answers'][] = ['key' => $key, 'label' => $label, 'type' => $questionType ?: 'text', 'type_label' => self::questionTypeLabel($questionType ?: 'text'), 'value' => $value];
            $report['summary']['answers']++;
        }

        $report['summary']['order_boxes'] = self::normaliseTotal($report['summary']['order_boxes']);
        $report['summary']['order_units'] = self::normaliseTotal($report['summary']['order_units']);
        $report['preview'] = self::buildPreview($report);
        return $report;
    }

    public static function exportTables(array $submissionRows): array {
        $summaryHeaders = [
            'ID','Data','Dia operacional','Formulário','Cliente','Campanha / Projeto','Rota ID','Data da rota',
            'Paragem ID','Loja / Local','Estado da paragem','Fecho operacional','Justificação','Operativo','Submetido por','Estado da submissão',
            'Check-in','Check-out','Duração visita (min)','Respostas','Referências','Ruturas','Produtos com presença',
            'Alterações antes/depois','Sugestões de encomenda','Total caixas','Total unidades','Comparativos',
            'Comparativos abaixo do objetivo','Ficheiros / Fotos','Resumo'
        ];
        $answerHeaders = ['Relatório ID','Data','Loja / Local','Formulário','Cliente','Campanha / Projeto','Operativo','Pergunta','Tipo de pergunta','Resposta'];
        $briefingHeaders = ['Relatório ID','Data','Loja / Local','Formulário','Cliente','Campanha / Projeto','Operativo','Briefing','Pergunta','Tipo de pergunta','Estado','Resposta'];
        $comparisonHeaders = ['Relatório ID','Data','Loja / Local','Formulário','Cliente','Campanha / Projeto','Operativo','Pergunta','Tipo de pergunta','Comparação','Referência A','Frentes A','Quota A (%)','Referência B','Frentes B','Quota B (%)','Total frentes','Objetivo (%)','Desvio (pp)','Estado','Quota anterior A (%)','Quota anterior B (%)','Semana de referência'];
        $productHeaders = ['Relatório ID','Data','Loja / Local','Formulário','Pergunta','Tipo de pergunta','Categoria','Referência','Produto','Valor','Antes','Depois','Estado','Alterado'];
        $orderHeaders = ['Relatório ID','Data','Loja / Local','Formulário','Pergunta','Tipo de pergunta','Categoria','Referência','Produto','Tipo','Capacidade (cl)','Unidades / caixa','Caixas','Unidades totais','Observações'];
        $fileHeaders = ['Relatório ID','Data','Loja / Local','Formulário','Pergunta','Tipo de pergunta','URL'];
        $detailHeaders = ['Tipo de registo','Relatório ID','Data','Loja / Local','Formulário','Cliente','Campanha / Projeto','Operativo','Pergunta','Tipo de pergunta','Categoria','Referência','Produto','Métrica','Valor','Antes','Depois','Observações','URL','Referência A','Frentes A','Quota A (%)','Referência B','Frentes B','Quota B (%)','Objetivo (%)','Desvio (pp)','Estado comparativo'];

        $summaryRows = [];
        $answerRows = [];
        $briefingRows = [];
        $comparisonRows = [];
        $productRows = [];
        $orderRows = [];
        $fileRows = [];
        $detailRows = [];

        foreach ($submissionRows as $row) {
            if (!is_array($row)) continue;
            $presentation = is_array($row['presentation'] ?? null) ? $row['presentation'] : ['answers'=>[],'product_questions'=>[],'order_questions'=>[],'comparison_questions'=>[],'files'=>[],'summary'=>[],'preview'=>''];
            $summary = is_array($presentation['summary'] ?? null) ? $presentation['summary'] : [];
            $context = self::exportContext($row);

            $visit = is_array($presentation['visit'] ?? null) ? $presentation['visit'] : [];
            $visitLabel = (string)($visit['label'] ?? '');
            $visitReason = (string)($visit['reason'] ?? '');
            $summaryRows[] = [
                $context['id'],$context['date'],$context['report_day'],$context['form'],$context['client'],$context['project'],
                $context['route_id'],$context['route_date'],$context['stop_id'],$context['location'],$context['stop_status'],$visitLabel,$visitReason,
                $context['owner'],$context['submitter'],$context['status'],$context['arrived_at'],$context['departed_at'],
                $context['duration_minutes'],(string)($summary['answers'] ?? 0),(string)($summary['product_rows'] ?? 0),
                (string)($summary['zero_products'] ?? 0),(string)($summary['positive_products'] ?? 0),
                (string)($summary['changed_products'] ?? 0),(string)($summary['orders'] ?? 0),
                (string)($summary['order_boxes'] ?? 0),(string)($summary['order_units'] ?? 0),
                (string)($summary['comparisons'] ?? 0),(string)($summary['comparisons_below_target'] ?? 0),
                (string)($summary['files'] ?? 0),(string)($presentation['preview'] ?? ''),
            ];

            if ($visitLabel !== '' || $visitReason !== '') {
                $detailRows[] = ['Fecho da visita',$context['id'],$context['date'],$context['location'],$context['form'],$context['client'],$context['project'],$context['owner'],'Fecho operacional','Visita','','','','Estado',$visitLabel,'','',$visitReason,'','','','','','','','','',''];
            }

            foreach ((array)($presentation['answers'] ?? []) as $answer) {
                $typeLabel = (string)($answer['type_label'] ?? self::questionTypeLabel((string)($answer['type'] ?? 'text')));
                $answerRows[] = [$context['id'],$context['date'],$context['location'],$context['form'],$context['client'],$context['project'],$context['owner'],(string)($answer['label'] ?? ''),$typeLabel,(string)($answer['value'] ?? '')];
                $detailRows[] = ['Resposta',$context['id'],$context['date'],$context['location'],$context['form'],$context['client'],$context['project'],$context['owner'],(string)($answer['label'] ?? ''),$typeLabel,'','','','Resposta',(string)($answer['value'] ?? ''),'','','','','','','','','','','','',''];
            }

            // fieldflow-client-report-scope-briefing-assignment-v1
            // Briefing is a first-class report dataset. Only questions already
            // resolved against the exact submission store are exported here.
            foreach ((array)($presentation['briefing_questions'] ?? []) as $question) {
                $typeLabel = self::questionTypeLabel((string)($question['type'] ?? 'text'));
                $answered = !empty($question['answered']);
                $value = $answered ? (string)($question['answer'] ?? '0') : 'Sem resposta';
                $briefingTitle = (string)($question['briefing_title'] ?? 'Briefing');
                $briefingRows[] = [
                    $context['id'],$context['date'],$context['location'],$context['form'],$context['client'],$context['project'],$context['owner'],
                    $briefingTitle,(string)($question['label'] ?? ''),$typeLabel,$answered ? 'Respondida' : 'Pendente',$value,
                ];
                $detailRows[] = [
                    'Briefing',$context['id'],$context['date'],$context['location'],$context['form'],$context['client'],$context['project'],$context['owner'],
                    (string)($question['label'] ?? ''),$typeLabel,'','','','Resposta',$value,'','',$briefingTitle,'','','','','','','','','','',
                ];
            }

            foreach ((array)($presentation['comparison_questions'] ?? []) as $question) {
                $typeLabel = (string)($question['type_label'] ?? self::questionTypeLabel((string)($question['type'] ?? 'share_space_matrix')));
                foreach ((array)($question['rows'] ?? []) as $comparison) {
                    $comparisonRows[] = [
                        $context['id'],$context['date'],$context['location'],$context['form'],$context['client'],$context['project'],$context['owner'],
                        (string)($question['label'] ?? ''),$typeLabel,(string)($comparison['metric'] ?? ''),(string)($comparison['reference_a'] ?? ''),
                        (string)($comparison['fronts_a'] ?? ''),(string)($comparison['share_a'] ?? ''),(string)($comparison['reference_b'] ?? ''),
                        (string)($comparison['fronts_b'] ?? ''),(string)($comparison['share_b'] ?? ''),(string)($comparison['total_fronts'] ?? ''),
                        (string)($comparison['target'] ?? ''),(string)($comparison['gap'] ?? ''),(string)($comparison['status_label'] ?? ''),
                        (string)($comparison['reference_share'] ?? ''),(string)($comparison['reference_competitor_share'] ?? ''),(string)($comparison['reference_week'] ?? ''),
                    ];
                    $detailRows[] = [
                        'Comparativo',$context['id'],$context['date'],$context['location'],$context['form'],$context['client'],$context['project'],$context['owner'],
                        (string)($question['label'] ?? ''),$typeLabel,'','','',(string)($comparison['metric'] ?? ''),(string)($comparison['share_a'] ?? ''),'','','Total frentes: '.(string)($comparison['total_fronts'] ?? ''),'',
                        (string)($comparison['reference_a'] ?? ''),(string)($comparison['fronts_a'] ?? ''),(string)($comparison['share_a'] ?? ''),
                        (string)($comparison['reference_b'] ?? ''),(string)($comparison['fronts_b'] ?? ''),(string)($comparison['share_b'] ?? ''),
                        (string)($comparison['target'] ?? ''),(string)($comparison['gap'] ?? ''),(string)($comparison['status_label'] ?? ''),
                    ];
                }
            }

            foreach ((array)($presentation['product_questions'] ?? []) as $question) {
                $typeLabel = (string)($question['type_label'] ?? self::questionTypeLabel((string)($question['type'] ?? 'product_matrix')));
                foreach ((array)($question['rows'] ?? []) as $product) {
                    $value = self::productDisplayValue($product);
                    $productRows[] = [
                        $context['id'],$context['date'],$context['location'],$context['form'],(string)($question['label'] ?? ''),$typeLabel,
                        (string)($product['category'] ?? ''),(string)($product['ref'] ?? ''),(string)($product['name'] ?? ''),
                        $value,(string)($product['before'] ?? ''),(string)($product['after'] ?? ''),
                        self::productStatusLabel((string)($product['status'] ?? '')),!empty($product['changed']) ? 'Sim' : 'Não',
                    ];
                    $detailRows[] = [
                        'Produto',$context['id'],$context['date'],$context['location'],$context['form'],$context['client'],$context['project'],$context['owner'],
                        (string)($question['label'] ?? ''),$typeLabel,(string)($product['category'] ?? ''),(string)($product['ref'] ?? ''),(string)($product['name'] ?? ''),
                        'Valor',$value,(string)($product['before'] ?? ''),(string)($product['after'] ?? ''),self::productStatusLabel((string)($product['status'] ?? '')),'','','','','','','','','',''
                    ];
                }
            }

            foreach ((array)($presentation['order_questions'] ?? []) as $question) {
                $typeLabel = (string)($question['type_label'] ?? self::questionTypeLabel((string)($question['type'] ?? 'order_suggestion')));
                foreach ((array)($question['rows'] ?? []) as $order) {
                    $orderRows[] = [
                        $context['id'],$context['date'],$context['location'],$context['form'],(string)($question['label'] ?? ''),$typeLabel,
                        (string)($order['category'] ?? ''),(string)($order['ref'] ?? ''),(string)($order['name'] ?? ''),
                        (string)($order['item_type'] ?? ''),(string)($order['capacity_cl'] ?? ''),(string)($order['units_per_case'] ?? ''),
                        (string)($order['order_qty'] ?? ''),(string)($order['units_total'] ?? ''),(string)($order['notes'] ?? ''),
                    ];
                    $orderMeta = [];
                    if ((string)($order['item_type'] ?? '') !== '') $orderMeta[] = 'Tipo: ' . (string)$order['item_type'];
                    if ((string)($order['capacity_cl'] ?? '') !== '') $orderMeta[] = 'Capacidade: ' . (string)$order['capacity_cl'] . ' cl';
                    if ((string)($order['units_per_case'] ?? '') !== '') $orderMeta[] = 'Unidades/caixa: ' . (string)$order['units_per_case'];
                    if ((string)($order['notes'] ?? '') !== '') $orderMeta[] = (string)$order['notes'];
                    $orderObservation = implode(' | ', $orderMeta);
                    $detailRows[] = [
                        'Encomenda',$context['id'],$context['date'],$context['location'],$context['form'],$context['client'],$context['project'],$context['owner'],
                        (string)($question['label'] ?? ''),$typeLabel,(string)($order['category'] ?? ''),(string)($order['ref'] ?? ''),(string)($order['name'] ?? ''),
                        'Caixas',(string)($order['order_qty'] ?? ''),'','',$orderObservation,'','','','','','','','','',''
                    ];
                    if ((string)($order['units_total'] ?? '') !== '') {
                        $detailRows[] = [
                            'Encomenda',$context['id'],$context['date'],$context['location'],$context['form'],$context['client'],$context['project'],$context['owner'],
                            (string)($question['label'] ?? ''),$typeLabel,(string)($order['category'] ?? ''),(string)($order['ref'] ?? ''),(string)($order['name'] ?? ''),
                            'Unidades totais',(string)($order['units_total'] ?? ''),'','',$orderObservation,'','','','','','','','','',''
                        ];
                    }
                }
            }

            foreach ((array)($presentation['files'] ?? []) as $file) {
                $typeLabel = (string)($file['type_label'] ?? self::questionTypeLabel((string)($file['type'] ?? 'file_upload')));
                $fileRows[] = [$context['id'],$context['date'],$context['location'],$context['form'],(string)($file['label'] ?? ''),$typeLabel,(string)($file['url'] ?? '')];
                $detailRows[] = ['Ficheiro',$context['id'],$context['date'],$context['location'],$context['form'],$context['client'],$context['project'],$context['owner'],(string)($file['label'] ?? ''),$typeLabel,'','','','URL','','','',(string)($file['label'] ?? ''),(string)($file['url'] ?? ''),'','','','','','','','',''];
            }
        }

        return [
            'summary' => ['name'=>'Submissões','headers'=>$summaryHeaders,'rows'=>$summaryRows],
            'answers' => ['name'=>'Respostas','headers'=>$answerHeaders,'rows'=>$answerRows],
            'briefing' => ['name'=>'Briefing','headers'=>$briefingHeaders,'rows'=>$briefingRows],
            'comparisons' => ['name'=>'Comparativos','headers'=>$comparisonHeaders,'rows'=>$comparisonRows],
            'products' => ['name'=>'Produtos','headers'=>$productHeaders,'rows'=>$productRows],
            'orders' => ['name'=>'Encomendas','headers'=>$orderHeaders,'rows'=>$orderRows],
            'files' => ['name'=>'Evidências','headers'=>$fileHeaders,'rows'=>$fileRows],
            'detail' => ['name'=>'Dados normalizados','headers'=>$detailHeaders,'rows'=>$detailRows],
        ];
    }

    private static function exportContext(array $row): array {
        $duration = '';
        if (isset($row['duration_s']) && $row['duration_s'] !== '' && is_numeric($row['duration_s'])) {
            $duration = round(((float)$row['duration_s']) / 60, 1);
            if (abs($duration - round($duration)) < 0.01) $duration = (int)round($duration);
        }
        return [
            'id' => (int)($row['submission_id'] ?? $row['id'] ?? 0) ?: (int)($row['id'] ?? 0),
            'date' => (string)($row['submitted_at'] ?? ''),
            'report_day' => (string)($row['report_day'] ?? ''),
            'form' => (string)($row['form_title'] ?: ('#' . (int)($row['form_id'] ?? 0))),
            'client' => (string)($row['client_name'] ?? ''),
            'project' => (string)($row['project_name'] ?? ''),
            'route_id' => (int)($row['route_id'] ?? $row['joined_route_id'] ?? 0),
            'route_date' => (string)($row['route_date'] ?? ''),
            'stop_id' => (int)($row['route_stop_id'] ?? $row['stop_row_id'] ?? 0),
            'location' => (string)($row['location_name'] ?? ''),
            'stop_status' => (string)($row['stop_status'] ?? ''),
            'owner' => (string)($row['owner_name'] ?? ''),
            'submitter' => (string)($row['user_name'] ?? ('User #' . (int)($row['user_id'] ?? 0))),
            'status' => (string)($row['status'] ?? ''),
            'arrived_at' => (string)($row['arrived_at'] ?? ''),
            'departed_at' => (string)($row['departed_at'] ?? ''),
            'duration_minutes' => (string)$duration,
        ];
    }

    private static function comparisonRows(array $answer): ?array {
        $decoded = self::decodeJson($answer);
        if ($decoded === null) return null;
        $isComparison = false;
        $rows = [];
        foreach ($decoded as $index => $row) {
            if (!is_array($row)) continue;
            if (array_key_exists('pr_fronts', $row) || array_key_exists('competitor_fronts', $row) || array_key_exists('pr_share', $row) || array_key_exists('competitor_share', $row)) $isComparison = true;
            if (!$isComparison && !in_array(sanitize_key((string)($answer['question_type'] ?? '')), ['share_space_matrix','comparative','comparison'], true)) continue;
            $referenceA = sanitize_text_field((string)($row['pr_label'] ?? $row['reference_a'] ?? $row['brand_label'] ?? 'Referência A'));
            $referenceB = sanitize_text_field((string)($row['competitor_label'] ?? $row['reference_b'] ?? $row['competitor'] ?? 'Referência B'));
            $metric = sanitize_text_field((string)($row['metric'] ?? $row['label'] ?? $row['metric_key'] ?? ('Comparativo ' . ($index + 1))));
            $frontsA = self::numericOrBlank($row['pr_fronts'] ?? $row['fronts_a'] ?? '');
            $frontsB = self::numericOrBlank($row['competitor_fronts'] ?? $row['fronts_b'] ?? '');
            $total = self::numericOrBlank($row['total_fronts'] ?? '');
            if ($total === '' && is_numeric($frontsA) && is_numeric($frontsB)) $total = self::normaliseTotal((float)$frontsA + (float)$frontsB);
            $shareA = self::numericOrBlank($row['pr_share'] ?? $row['share_a'] ?? '');
            $shareB = self::numericOrBlank($row['competitor_share'] ?? $row['share_b'] ?? '');
            if ($shareA === '' && is_numeric($frontsA) && is_numeric($total) && (float)$total > 0) $shareA = round(((float)$frontsA / (float)$total) * 100, 2);
            if ($shareB === '' && is_numeric($frontsB) && is_numeric($total) && (float)$total > 0) $shareB = round(((float)$frontsB / (float)$total) * 100, 2);
            $target = self::numericOrBlank($row['target'] ?? $row['target_share'] ?? '');
            $gap = self::numericOrBlank($row['gap'] ?? $row['deviation'] ?? '');
            if ($gap === '' && is_numeric($shareA) && is_numeric($target)) $gap = round((float)$shareA - (float)$target, 2);
            $status = sanitize_key((string)($row['status'] ?? ''));
            if ($status === '' && is_numeric($gap)) $status = (float)$gap < 0 ? 'below' : 'on_or_above';
            if ($status === '' && is_numeric($total) && (float)$total <= 0) $status = 'no_fronts';
            $rows[] = [
                'metric_key' => sanitize_key((string)($row['metric_key'] ?? 'metric_' . ($index + 1))),
                'metric' => $metric,
                'reference_a' => $referenceA,
                'fronts_a' => $frontsA,
                'share_a' => $shareA,
                'reference_b' => $referenceB,
                'fronts_b' => $frontsB,
                'share_b' => $shareB,
                'total_fronts' => $total,
                'target' => $target,
                'gap' => $gap,
                'status' => $status,
                'status_label' => self::comparisonStatusLabel($status),
                'reference_share' => self::numericOrBlank($row['reference_share'] ?? ''),
                'reference_competitor_share' => self::numericOrBlank($row['reference_competitor_share'] ?? ''),
                'reference_week' => sanitize_text_field((string)($row['reference_week'] ?? '')),
            ];
        }
        return $isComparison ? $rows : null;
    }

    private static function inferQuestionType(array $answer): string {
        $declared = sanitize_key((string)($answer['question_type'] ?? ''));
        if ($declared !== '') return $declared;
        $decoded = self::decodeJson($answer);
        if ($decoded !== null) {
            $first = reset($decoded);
            if (is_array($first)) {
                if (array_key_exists('pr_fronts', $first) || array_key_exists('competitor_fronts', $first) || array_key_exists('pr_share', $first)) return 'share_space_matrix';
                if (array_key_exists('order_qty', $first) || array_key_exists('boxes', $first) || array_key_exists('units_total', $first)) return 'order_suggestion';
                if (array_key_exists('qty', $first) || array_key_exists('before', $first) || array_key_exists('after', $first) || array_key_exists('ref', $first)) return 'product_matrix';
            }
            return 'json';
        }
        if (isset($answer['value_number']) && $answer['value_number'] !== null && $answer['value_number'] !== '') return 'number';
        $text = trim((string)($answer['value_text'] ?? ''));
        if ($text !== '' && preg_match('#^https?://#i', $text)) return 'file_upload';
        return 'text';
    }

    private static function questionTypeLabel(string $type): string {
        $type = sanitize_key($type);
        $labels = [
            'text'=>'Texto','textarea'=>'Texto longo','number'=>'Número','currency'=>'Moeda','percent'=>'Percentagem',
            'select'=>'Lista','radio'=>'Escolha única','evidence_check'=>'Conformidade + evidência','multi_select'=>'Escolha múltipla','checkbox'=>'Checkbox','checkbox_group'=>'Checkboxes',
            'date'=>'Data','time'=>'Hora','image_upload'=>'Imagem','categorized_image_upload'=>'Imagens por categoria','file_upload'=>'Ficheiro',
            'product_matrix'=>'Matriz de produtos','order_suggestion'=>'Sugestão de encomenda','share_space_matrix'=>'Comparativo automático',
            'comparative'=>'Comparativo automático','comparison'=>'Comparativo automático','json'=>'Dados estruturados',
        ];
        return $labels[$type] ?? ($type !== '' ? ucwords(str_replace('_', ' ', $type)) : 'Texto');
    }

    private static function comparisonStatusLabel(string $status): string {
        if ($status === 'below') return 'Abaixo do objetivo';
        if (in_array($status, ['on_or_above','above','met','complete'], true)) return 'No objetivo ou acima';
        if ($status === 'no_fronts') return 'Sem frentes';
        return $status !== '' ? ucwords(str_replace('_', ' ', $status)) : 'Por classificar';
    }

    private static function numericOrBlank($value) {
        if ($value === null || $value === '') return '';
        if (!is_numeric($value)) return sanitize_text_field((string)$value);
        return self::normaliseTotal((float)$value);
    }

    private static function orderRows(array $answer): ?array {
        $decoded = self::decodeJson($answer);
        if ($decoded === null) return null;
        $isOrder = false;
        $rows = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) continue;
            if (array_key_exists('order_qty', $row) || array_key_exists('boxes', $row) || array_key_exists('notes', $row) || array_key_exists('observations', $row)) $isOrder = true;
            $category = sanitize_text_field((string)($row['category'] ?? $row['categoria'] ?? ''));
            $ref = sanitize_text_field((string)($row['ref'] ?? $row['reference'] ?? $row['code'] ?? ''));
            $name = sanitize_text_field((string)($row['name'] ?? $row['product'] ?? ''));
            $itemType = sanitize_text_field((string)($row['item_type'] ?? $row['type'] ?? ''));
            $capacity = $row['capacity_cl'] ?? '';
            $unitsPerCase = $row['units_per_case'] ?? '';
            $orderQty = $row['order_qty'] ?? $row['boxes'] ?? '';
            $unitsTotal = $row['units_total'] ?? (($orderQty !== '' && $unitsPerCase !== '' && is_numeric($orderQty) && is_numeric($unitsPerCase)) ? (float)$orderQty * (float)$unitsPerCase : '');
            $notes = sanitize_textarea_field((string)($row['notes'] ?? $row['observations'] ?? ''));
            if ($category === '' && $ref === '' && $name === '' && $orderQty === '' && $notes === '') continue;
            $rows[] = [
                'category'=>$category,'ref'=>$ref,'name'=>$name,'item_type'=>$itemType,
                'capacity_cl'=>$capacity,'units_per_case'=>$unitsPerCase,'order_qty'=>$orderQty,
                'units_total'=>$unitsTotal,'notes'=>$notes,
            ];
        }
        return $isOrder ? $rows : null;
    }

    private static function productRows(array $answer): ?array {
        $decoded = self::decodeJson($answer);
        if ($decoded === null) return null;
        $isMatrix = false;
        $rows = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) continue;
            if (array_key_exists('qty', $row) || array_key_exists('quantity', $row) || array_key_exists('ref', $row) || array_key_exists('reference', $row)) $isMatrix = true;
            $category = sanitize_text_field((string)($row['category'] ?? $row['categoria'] ?? ''));
            $ref = sanitize_text_field((string)($row['ref'] ?? $row['reference'] ?? ''));
            $name = sanitize_text_field((string)($row['name'] ?? $row['product'] ?? ''));
            $qty = $row['qty'] ?? ($row['after'] ?? ($row['quantity'] ?? ''));
            $before = array_key_exists('before', $row) ? $row['before'] : null;
            $after = array_key_exists('after', $row) ? $row['after'] : null;
            if ($category === '' && $ref === '' && $name === '' && $qty === '' && $before === null && $after === null) continue;
            $out = ['category'=>$category,'ref'=>$ref,'name'=>$name,'qty'=>$qty];
            if ($before !== null || $after !== null) {
                $out['before'] = $before;
                $out['after'] = $after !== null ? $after : $qty;
            }
            $rows[] = $out;
        }
        return $isMatrix ? $rows : null;
    }

    private static function decorateProductRow(array $row): array {
        $hasBeforeAfter = array_key_exists('before', $row) || array_key_exists('after', $row);
        $before = $hasBeforeAfter ? ($row['before'] ?? '') : '';
        $after = $hasBeforeAfter ? ($row['after'] ?? ($row['qty'] ?? '')) : '';
        $value = $hasBeforeAfter ? $after : ($row['qty'] ?? '');
        $status = 'unknown';
        if (is_numeric($value)) $status = ((float)$value <= 0) ? 'zero' : 'positive';
        elseif ((string)$value !== '') $status = 'positive';
        $changed = $hasBeforeAfter && (string)$before !== (string)$after;
        return [
            'category' => sanitize_text_field((string)($row['category'] ?? '')),
            'ref' => sanitize_text_field((string)($row['ref'] ?? '')),
            'name' => sanitize_text_field((string)($row['name'] ?? '')),
            'qty' => $row['qty'] ?? '',
            'before' => $before,
            'after' => $after,
            'value' => $value,
            'status' => $status,
            'changed' => $changed,
        ];
    }

    private static function groupRowsByCategory(array $rows): array {
        $groups = [];
        foreach ($rows as $row) {
            $category = trim((string)($row['category'] ?? '')) ?: 'Sem categoria';
            if (!isset($groups[$category])) $groups[$category] = ['name'=>$category,'rows'=>[],'count'=>0,'boxes'=>0,'units'=>0];
            $groups[$category]['rows'][] = $row;
            $groups[$category]['count']++;
            if (is_numeric($row['order_qty'] ?? null)) $groups[$category]['boxes'] += (float)$row['order_qty'];
            if (is_numeric($row['units_total'] ?? null)) $groups[$category]['units'] += (float)$row['units_total'];
        }
        uasort($groups, fn($a,$b) => strnatcasecmp((string)$a['name'], (string)$b['name']));
        foreach ($groups as &$group) {
            $group['boxes'] = self::normaliseTotal($group['boxes']);
            $group['units'] = self::normaliseTotal($group['units']);
        }
        unset($group);
        return array_values($groups);
    }

    private static function groupProductRowsByCategory(array $rows): array {
        $groups = [];
        foreach ($rows as $row) {
            $category = trim((string)($row['category'] ?? '')) ?: 'Sem categoria';
            if (!isset($groups[$category])) $groups[$category] = ['name'=>$category,'rows'=>[],'count'=>0,'zero'=>0,'positive'=>0,'changed'=>0];
            $groups[$category]['rows'][] = $row;
            $groups[$category]['count']++;
            if (($row['status'] ?? '') === 'zero') $groups[$category]['zero']++;
            elseif (($row['status'] ?? '') === 'positive') $groups[$category]['positive']++;
            if (!empty($row['changed'])) $groups[$category]['changed']++;
        }
        uasort($groups, fn($a,$b) => strnatcasecmp((string)$a['name'], (string)$b['name']));
        return array_values($groups);
    }

    private static function orderRowHasValue(array $row): bool {
        $qty = $row['order_qty'] ?? '';
        $units = $row['units_total'] ?? '';
        $notes = trim((string)($row['notes'] ?? ''));
        if ($notes !== '') return true;
        if ($qty !== '' && is_numeric($qty) && (float)$qty > 0) return true;
        if ($units !== '' && is_numeric($units) && (float)$units > 0) return true;
        return false;
    }

    private static function decodeJson(array $answer): ?array {
        $json = (string)($answer['value_json'] ?? '');
        if ($json === '') return null;
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) return null;
        return $decoded;
    }

    private static function fileItems($value): array {
        $out = [];
        $walk = static function($node, string $categoryLabel = '') use (&$walk, &$out): void {
            if (is_array($node)) {
                $nextCategory = sanitize_text_field((string)($node['category_label'] ?? $node['category'] ?? $categoryLabel));
                $directUrl = isset($node['url']) && is_scalar($node['url']) ? esc_url_raw((string)$node['url']) : '';
                if ($directUrl !== '' && filter_var($directUrl, FILTER_VALIDATE_URL)) {
                    $out[$directUrl] = [
                        'url' => $directUrl,
                        'name' => sanitize_file_name((string)($node['name'] ?? $node['filename'] ?? '')),
                        'type' => sanitize_text_field((string)($node['type'] ?? $node['mime_type'] ?? '')),
                        'category_label' => $nextCategory,
                    ];
                }
                foreach ($node as $key => $child) {
                    if (in_array((string)$key, ['url','name','filename','type','mime_type','category','category_key','category_label'], true)) continue;
                    $walk($child, $nextCategory);
                }
                return;
            }
            if (!is_string($node)) return;
            $candidate = trim($node);
            if ($candidate === '' || !filter_var($candidate, FILTER_VALIDATE_URL)) return;
            $candidate = esc_url_raw($candidate);
            if ($candidate === '') return;
            $out[$candidate] = ['url'=>$candidate,'name'=>'','type'=>'','category_label'=>$categoryLabel];
        };
        $walk($value, '');
        return array_values($out);
    }

    private static function rawValue(array $answer) {
        $json = (string)($answer['value_json'] ?? '');
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE) return $decoded;
        }
        if (array_key_exists('value_number', $answer) && $answer['value_number'] !== null && ($answer['value_text'] ?? null) === null) return $answer['value_number'];
        return $answer['value_text'] ?? '';
    }

    private static function valueToText($value): string {
        if (is_array($value)) {
            $flat = [];
            array_walk_recursive($value, function($item) use (&$flat) {
                if (is_scalar($item) && (string)$item !== '') $flat[] = (string)$item;
            });
            return implode(', ', array_values(array_unique($flat)));
        }
        if (is_bool($value)) return $value ? 'Sim' : 'Não';
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private static function hasValue($value): bool {
        if (is_array($value)) return count($value) > 0;
        if ($value === null) return false;
        return trim((string)$value) !== '';
    }

    private static function looksLikeFile(string $label, string $value): bool {
        if (!preg_match('#^https?://#i', $value)) return false;
        if (preg_match('/\.(jpe?g|png|gif|webp|heic|pdf|docx?|xlsx?|csv)(\?.*)?$/i', $value)) return true;
        return (bool)preg_match('/foto|fotografia|imagem|ficheiro|upload|evidência|evidencia/i', $label);
    }

    private static function buildPreview(array $report): string {
        $parts = [];
        foreach (array_slice((array)($report['answers'] ?? []), 0, 3) as $answer) {
            $value = self::shorten((string)($answer['value'] ?? ''), 70);
            if ($value !== '') $parts[] = (string)($answer['label'] ?? 'Resposta') . ': ' . $value;
        }
        $summary = (array)($report['summary'] ?? []);
        if (!empty($summary['zero_products'])) $parts[] = (int)$summary['zero_products'] . ' ruturas';
        if (!empty($summary['orders'])) $parts[] = (int)$summary['orders'] . ' encomendas';
        if (!empty($summary['files'])) $parts[] = (int)$summary['files'] . ' evidências';
        if (!$parts) $parts[] = 'Sem respostas preenchidas';
        return implode(' | ', $parts);
    }

    private static function shorten(string $value, int $max): string {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?: $value);
        if (function_exists('mb_strlen') && mb_strlen($value) > $max) return rtrim(mb_substr($value, 0, $max - 1)) . '…';
        if (strlen($value) > $max) return rtrim(substr($value, 0, $max - 3)) . '...';
        return $value;
    }

    private static function normaliseTotal($value) {
        $value = (float)$value;
        return abs($value - round($value)) < 0.00001 ? (int)round($value) : round($value, 2);
    }

    private static function productDisplayValue(array $product): string {
        if (array_key_exists('before', $product) && ((string)($product['before'] ?? '') !== '' || (string)($product['after'] ?? '') !== '')) {
            return (string)($product['before'] ?? '') . ' > ' . (string)($product['after'] ?? '');
        }
        return (string)($product['value'] ?? $product['qty'] ?? '');
    }

    private static function productStatusLabel(string $status): string {
        if ($status === 'zero') return 'Rutura / zero';
        if ($status === 'positive') return 'Com presença';
        return 'Sem classificação';
    }
}
