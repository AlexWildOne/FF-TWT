<?php
namespace RoutesPro\Reporting;

if (!defined('ABSPATH')) exit;

/**
 * Deterministic daily commercial interpretation of the canonical report.
 *
 * Facts are calculated here. Narrative is assembled only from those facts,
 * never by inventing values or forcing empty sections into the email/export.
 */
final class DailyCommercialReportService {
    public const VERSION = '1.2.1';
    public const CONTRACT = 'fieldflow-mail-intelligence-daily-commercial-reporting-v1';

    public static function build(array $report): array {
        $k = is_array($report['kpis'] ?? null) ? $report['kpis'] : [];
        $stops = array_values(array_filter((array)($report['routing']['stops'] ?? []), 'is_array'));
        $frontRows = array_values(array_filter((array)($report['fronts']['rows'] ?? []), 'is_array'));
        $spaceRows = array_values(array_filter((array)($report['extra_spaces']['rows'] ?? []), 'is_array'));
        $orderRows = array_values(array_filter((array)($report['orders']['rows'] ?? []), 'is_array'));
        $briefRows = array_values(array_filter((array)($report['briefing']['rows'] ?? []), 'is_array'));
        $evidenceRows = array_values(array_filter((array)($report['evidence']['rows'] ?? []), 'is_array'));
        $mobility = is_array($report['mobility'] ?? null) ? $report['mobility'] : [];
        $mobilitySummary = is_array($mobility['summary'] ?? null) ? $mobility['summary'] : [];
        $mobilityDays = array_values(array_filter((array)($mobility['by_day'] ?? []), 'is_array'));

        $planned = (int)($k['visits_planned'] ?? count($stops));
        $completed = (int)($k['visits_completed'] ?? 0);
        $justified = (int)($k['visits_justified'] ?? 0);
        $remaining = (int)($k['visits_remaining'] ?? max(0, $planned - $completed - $justified));
        $executionRate = (float)($k['execution_rate'] ?? ($planned > 0 ? round(($completed / $planned) * 100, 1) : 0));
        $closureRate = (float)($k['operational_closure_rate'] ?? ($planned > 0 ? round((($completed + $justified) / $planned) * 100, 1) : 0));

        $exceptions = [];
        $visitRows = [];
        foreach ($stops as $stop) {
            $status = sanitize_key((string)($stop['status'] ?? ''));
            $reason = trim((string)($stop['justification'] ?? ''));
            $executed = in_array($status, ['done','completed'], true);
            $isJustified = $status === 'failed' && $reason !== '';
            $terminal = $executed || $isJustified;
            $result = $executed ? 'Executada' : ($isJustified ? 'Não executada, justificada' : 'Não executada');
            $row = $stop;
            $row['executed'] = $executed;
            $row['justified'] = $isJustified;
            $row['terminal'] = $terminal;
            $row['result_label'] = $result;
            $visitRows[] = $row;
            if (!$executed) {
                $exceptions[] = [
                    'severity'=>$isJustified ? 'attention' : 'critical',
                    'route_id'=>absint($stop['route_id'] ?? 0),
                    'location_id'=>absint($stop['location_id'] ?? 0),
                    'location'=>(string)($stop['location_name'] ?? ''),
                    'status'=>$status,
                    'justified'=>$isJustified,
                    'reason'=>$reason,
                    'detail'=>$isJustified
                        ? 'Visita não executada, justificada: ' . $reason
                        : 'Visita não executada' . ($reason !== '' ? ': ' . $reason : ', sem justificação registada.'),
                ];
            }
        }

        $locationFronts = [];
        foreach ($frontRows as $row) {
            $location = trim((string)($row['location'] ?? '')) ?: 'Loja não identificada';
            $locationId = absint($row['location_id'] ?? 0);
            $locationKey = $locationId ? 'id:' . $locationId : 'name:' . $location;
            $difference = self::numeric($row['difference'] ?? 0) ?? 0.0;
            $before = self::numeric($row['before'] ?? null);
            $after = self::numeric($row['after'] ?? null);
            if (!isset($locationFronts[$locationKey])) {
                $locationFronts[$locationKey] = ['location_id'=>$locationId,'location'=>$location,'before'=>0.0,'after'=>0.0,'delta'=>0.0,'items'=>[]];
            }
            if ($before !== null) $locationFronts[$locationKey]['before'] += $before;
            if ($after !== null) $locationFronts[$locationKey]['after'] += $after;
            $locationFronts[$locationKey]['delta'] += $difference;
            if (abs($difference) > 0.0001) {
                $label = trim((string)($row['product'] ?? '')) ?: trim((string)($row['category'] ?? '')) ?: trim((string)($row['question'] ?? 'Frentes'));
                $locationFronts[$locationKey]['items'][] = ['label'=>$label,'delta'=>round($difference, 2)];
            }
        }

        $gains = [];
        $losses = [];
        $stableLocations = 0;
        foreach ($locationFronts as $entry) {
            $entry['before'] = round((float)$entry['before'], 2);
            $entry['after'] = round((float)$entry['after'], 2);
            $entry['delta'] = round((float)$entry['delta'], 2);
            $entry['growth_percent'] = $entry['before'] != 0 ? round(($entry['delta'] / $entry['before']) * 100, 1) : null;
            usort($entry['items'], static fn(array $a, array $b): int => abs((float)$b['delta']) <=> abs((float)$a['delta']));
            $entry['items'] = array_slice($entry['items'], 0, 5);
            if ($entry['delta'] > 0) $gains[] = $entry;
            elseif ($entry['delta'] < 0) $losses[] = $entry;
            else $stableLocations++;
        }
        usort($gains, static fn(array $a, array $b): int => (float)$b['delta'] <=> (float)$a['delta']);
        usort($losses, static fn(array $a, array $b): int => (float)$a['delta'] <=> (float)$b['delta']);

        $netFronts = round((float)($k['fronts_gain'] ?? 0), 2);
        $frontsBefore = round((float)($k['fronts_before'] ?? 0), 2);
        $frontsAfter = round((float)($k['fronts_after'] ?? 0), 2);
        $frontGrowth = ($k['fronts_growth_percent'] ?? null) === null ? null : (float)$k['fronts_growth_percent'];
        $orderSubmissions = (int)($k['order_suggestions'] ?? 0);
        $orderLines = (int)($k['order_lines'] ?? count($orderRows));
        $orderBoxes = (float)($k['order_boxes'] ?? 0);
        $extraSpaces = (int)($k['extra_spaces'] ?? count($spaceRows));
        $briefQuestions = (int)($k['briefing_questions'] ?? 0);
        $briefAnswered = (int)($k['briefing_answered'] ?? 0);
        $briefTargetAssignments = (int)($k['briefing_target_assignments'] ?? $briefQuestions);
        $briefAnsweredAssignments = (int)($k['briefing_answered_assignments'] ?? $briefAnswered);
        $briefPendingAssignments = (int)($k['briefing_pending_assignments'] ?? max(0, $briefTargetAssignments - $briefAnsweredAssignments));
        $briefTargetStores = (int)($k['briefing_target_stores'] ?? 0);
        $briefCoverage = (float)($k['briefing_coverage_percent'] ?? 0);

        $capabilities = [
            'visits'=>$planned > 0 || !empty($stops),
            'fronts'=>!empty($frontRows) || $frontsBefore != 0.0 || $frontsAfter != 0.0,
            'extra_spaces'=>!empty($spaceRows) || $extraSpaces > 0,
            'orders'=>!empty($orderRows) || $orderLines > 0 || $orderSubmissions > 0,
            'briefing'=>!empty($briefRows) || $briefTargetAssignments > 0,
            'evidence'=>!empty($evidenceRows) || (int)($k['evidence_files'] ?? 0) > 0,
            'mobility'=>!empty($mobilityDays) || (int)($mobilitySummary['closed_routes'] ?? 0) > 0,
        ];

        $alerts = [];
        foreach ($exceptions as $exception) {
            $alerts[] = [
                'severity'=>(string)$exception['severity'],
                'type'=>'visit',
                'location'=>(string)$exception['location'],
                'title'=>!empty($exception['justified']) ? 'Visita não executada, justificada' : 'Visita por regularizar',
                'detail'=>(string)$exception['detail'],
            ];
        }
        foreach (array_slice($losses, 0, 8) as $loss) {
            $alerts[] = [
                'severity'=>abs((float)$loss['delta']) >= 5 ? 'critical' : 'attention',
                'type'=>'fronts_loss',
                'location'=>(string)$loss['location'],
                'title'=>'Perda de frentes',
                'detail'=>(string)$loss['location'] . ': ' . self::signed((float)$loss['delta']) . ' frentes líquidas.',
            ];
        }
        if ($capabilities['briefing'] && $briefTargetAssignments > 0 && $briefPendingAssignments > 0) {
            $alerts[] = [
                'severity'=>'attention', 'type'=>'briefing', 'location'=>'', 'title'=>'Briefing incompleto nas lojas alvo',
                'detail'=>$briefPendingAssignments . ' resposta(s) de briefing pendente(s), considerando apenas perguntas mapeadas às lojas presentes nesta rota/período.',
            ];
        }

        $opportunities = [];
        foreach (array_slice($gains, 0, 5) as $gain) {
            $opportunities[] = ['type'=>'fronts_gain','location'=>(string)$gain['location'],'value'=>(float)$gain['delta'],'detail'=>(string)$gain['location'] . ': ' . self::signed((float)$gain['delta']) . ' frentes.'];
        }
        if ($extraSpaces > 0) $opportunities[] = ['type'=>'implementation','location'=>'','value'=>$extraSpaces,'detail'=>$extraSpaces . ' conquista(s)/implementação(ões) positiva(s) registada(s).'];
        if ($orderLines > 0) $opportunities[] = ['type'=>'order','location'=>'','value'=>$orderLines,'detail'=>$orderLines . ' linha(s) de sugestão de encomenda, ' . self::plain($orderBoxes) . ' caixa(s).'];

        $facts = [
            'visits'=>[
                'planned'=>$planned,'completed'=>$completed,'justified'=>$justified,'remaining'=>$remaining,
                'execution_rate'=>$executionRate,'closure_rate'=>$closureRate,
            ],
            'fronts'=>[
                'before'=>$frontsBefore,'after'=>$frontsAfter,'net'=>$netFronts,'growth_percent'=>$frontGrowth,
                'gain_locations'=>count($gains),'loss_locations'=>count($losses),'stable_locations'=>$stableLocations,
            ],
            'commercial'=>[
                'extra_spaces'=>$extraSpaces,
                'order_suggestions'=>$orderSubmissions,
                'order_lines'=>$orderLines,
                'order_boxes'=>$orderBoxes,
                'evidence_files'=>(int)($k['evidence_files'] ?? 0),
                'briefing_coverage_percent'=>$briefCoverage,
                'briefing_questions'=>$briefQuestions,
                'briefing_target_assignments'=>$briefTargetAssignments,
                'briefing_answered_assignments'=>$briefAnsweredAssignments,
                'briefing_pending_assignments'=>$briefPendingAssignments,
                'briefing_target_stores'=>$briefTargetStores,
            ],
            'mobility'=>[
                'distance_km'=>(float)($mobilitySummary['distance_km'] ?? 0),
                'access_distance_km'=>(float)($mobilitySummary['access_distance_km'] ?? 0),
                'inter_stop_distance_km'=>(float)($mobilitySummary['inter_stop_distance_km'] ?? 0),
                'return_distance_km'=>(float)($mobilitySummary['return_distance_km'] ?? 0),
                'energy_cost_eur'=>(float)($mobilitySummary['energy_cost_eur'] ?? 0),
                'toll_cost_eur'=>(float)($mobilitySummary['toll_cost_eur'] ?? 0),
                'fuel_tolls_cost_eur'=>(float)($mobilitySummary['fuel_tolls_cost_eur'] ?? ((float)($mobilitySummary['energy_cost_eur'] ?? 0) + (float)($mobilitySummary['toll_cost_eur'] ?? 0))),
                'total_mobility_cost_eur'=>(float)($mobilitySummary['total_mobility_cost_eur'] ?? 0),
                'estimated_litres'=>(float)($mobilitySummary['estimated_litres'] ?? 0),
                'estimated_kwh'=>(float)($mobilitySummary['estimated_kwh'] ?? 0),
                'closed_routes'=>(int)($mobilitySummary['closed_routes'] ?? 0),
                'costed_routes'=>(int)($mobilitySummary['costed_routes'] ?? 0),
                'incomplete_routes'=>(int)($mobilitySummary['incomplete_routes'] ?? 0),
                'open_routes_excluded'=>(int)($mobilitySummary['open_routes_excluded'] ?? 0),
                'recovered_routes'=>(int)($mobilitySummary['recovered_routes'] ?? 0),
                'partial'=>!empty($mobilitySummary['partial']),
                'days'=>$mobilityDays,
            ],
        ];

        return [
            'contract'=>self::CONTRACT,
            'version'=>self::VERSION,
            'capabilities'=>$capabilities,
            'facts'=>$facts,
            'visits'=>$visitRows,
            'exceptions'=>$exceptions,
            'gains'=>$gains,
            'losses'=>$losses,
            'alerts'=>$alerts,
            'opportunities'=>$opportunities,
            'mobility'=>$mobility,
            'client'=>self::clientNarrative($facts, $capabilities, $gains, $losses, $exceptions, $extraSpaces, $orderLines, $orderBoxes),
            'merchandiser'=>self::merchandiserNarrative($facts, $capabilities, $gains, $losses, $exceptions, $alerts, $extraSpaces, $orderLines),
        ];
    }

    private static function clientNarrative(array $facts, array $capabilities, array $gains, array $losses, array $exceptions, int $extraSpaces, int $orderLines, float $orderBoxes): array {
        $v = $facts['visits'];
        $f = $facts['fronts'];
        $paragraphs = [];
        if (!empty($capabilities['visits'])) {
            if ($v['planned'] > 0 && $v['completed'] === $v['planned']) {
                $paragraphs[] = 'A operação foi concluída a 100%, com as ' . $v['planned'] . ' lojas planeadas executadas.';
            } else {
                $text = 'A operação terminou com ' . self::pct($v['execution_rate']) . ' de execução, ' . $v['completed'] . ' de ' . $v['planned'] . ' lojas realizadas.';
                if ($v['justified'] > 0) $text .= ' ' . $v['justified'] . ' visita(s) não executada(s) ficaram justificadas.';
                if ($v['remaining'] > 0) $text .= ' Permanecem ' . $v['remaining'] . ' visita(s) por regularizar.';
                $paragraphs[] = $text;
            }
        }
        if (!empty($capabilities['fronts'])) {
            if ($f['net'] > 0) {
                $text = 'Comercialmente, o período fechou com um ganho líquido de ' . self::signed($f['net']) . ' frentes';
                if ($f['gain_locations'] > 0) $text .= ', distribuído por ' . $f['gain_locations'] . ' loja(s) com crescimento';
                $paragraphs[] = $text . '.';
            } elseif ($f['net'] < 0) {
                $paragraphs[] = 'Comercialmente, foi registada uma perda líquida de ' . self::signed($f['net']) . ' frentes, concentrada em ' . $f['loss_locations'] . ' loja(s), que devem ser acompanhadas.';
            } else {
                $paragraphs[] = 'O saldo líquido de frentes manteve-se estável no âmbito analisado.';
            }
        }
        $commercialBits = [];
        if ($extraSpaces > 0) $commercialBits[] = $extraSpaces . ' nova(s) conquista(s)/implementação(ões)';
        if ($orderLines > 0) $commercialBits[] = $orderLines . ' linha(s) de oportunidade de encomenda, ' . self::plain($orderBoxes) . ' caixa(s)';
        if ($commercialBits) $paragraphs[] = 'Foram ainda identificadas ' . implode(' e ', $commercialBits) . '.';
        if ($exceptions) $paragraphs[] = 'As exceções do dia estão identificadas abaixo com a respetiva loja e justificação, quando existente.';
        if (!empty($capabilities['mobility'])) {
            $m = is_array($facts['mobility'] ?? null) ? $facts['mobility'] : [];
            $prefix = !empty($m['partial']) ? 'A mobilidade apurada é parcial' : 'A mobilidade apurada';
            if ((int)($m['recovered_routes'] ?? 0) > 0) $prefix .= ', com estimativa recuperada a partir dos km conhecidos';
            $period = count((array)($m['days'] ?? [])) > 1 ? ' no período' : ' no dia';
            $distanceText = ((float)($m['distance_km'] ?? 0) > 0 || ((int)($m['closed_routes'] ?? 0) > 0 && (int)($m['incomplete_routes'] ?? 0) === 0))
                ? self::km((float)($m['distance_km'] ?? 0)) . ' de rota concluída'
                : 'quilometragem ainda por apurar';
            $costText = (int)($m['costed_routes'] ?? 0) > 0
                ? self::eur((float)($m['energy_cost_eur'] ?? 0)) . ' de combustível/energia estimado e ' . self::eur((float)($m['toll_cost_eur'] ?? 0)) . ' de portagens'
                : 'custos de combustível/energia e portagens ainda por calcular';
            $paragraphs[] = $prefix . $period . ': ' . $distanceText . ', ' . $costText . '.';
        }

        $highlights = [];
        foreach (array_slice($gains, 0, 3) as $gain) $highlights[] = ['label'=>(string)$gain['location'],'value'=>self::signed((float)$gain['delta']) . ' frentes','detail'=>self::itemDetail($gain)];
        foreach (array_slice($losses, 0, 2) as $loss) $highlights[] = ['label'=>(string)$loss['location'],'value'=>self::signed((float)$loss['delta']) . ' frentes','detail'=>'Ponto de atenção comercial.'];

        return [
            'eyebrow'=>'Visão comercial do dia',
            'headline'=>self::clientHeadline($facts, count($exceptions)),
            'paragraphs'=>$paragraphs,
            'highlights'=>$highlights,
            'show_alerts'=>!empty($exceptions) || !empty($losses),
        ];
    }

    private static function merchandiserNarrative(array $facts, array $capabilities, array $gains, array $losses, array $exceptions, array $alerts, int $extraSpaces, int $orderLines): array {
        $v = $facts['visits'];
        $f = $facts['fronts'];
        $paragraphs = [];
        if (!empty($capabilities['visits'])) {
            $text = 'Concluíste ' . $v['completed'] . ' de ' . $v['planned'] . ' visitas planeadas, ' . self::pct($v['execution_rate']) . ' de execução.';
            if ($v['justified'] > 0) $text .= ' Tens ' . $v['justified'] . ' visita(s) não executada(s) justificadas.';
            if ($v['remaining'] > 0) $text .= ' Existem ' . $v['remaining'] . ' visita(s) que precisam de regularização.';
            $paragraphs[] = $text;
        }
        if (!empty($capabilities['fronts'])) {
            if ($f['net'] > 0) $paragraphs[] = 'O teu saldo comercial foi positivo, com ' . self::signed($f['net']) . ' frentes líquidas.';
            elseif ($f['net'] < 0) $paragraphs[] = 'O saldo comercial registou ' . self::signed($f['net']) . ' frentes líquidas. Revê as lojas assinaladas nos alertas.';
        }
        $positive = [];
        if ($extraSpaces > 0) $positive[] = $extraSpaces . ' conquista(s)/implementação(ões)';
        if ($orderLines > 0) $positive[] = $orderLines . ' linha(s) de oportunidade de encomenda';
        if ($positive) $paragraphs[] = 'Também registaste ' . implode(' e ', $positive) . '.';
        if (!empty($capabilities['mobility'])) {
            $m = is_array($facts['mobility'] ?? null) ? $facts['mobility'] : [];
            $prefix = !empty($m['partial']) ? 'A mobilidade apurada é parcial' : 'Na mobilidade';
            if ((int)($m['recovered_routes'] ?? 0) > 0) $prefix .= ', com estimativa recuperada a partir dos km conhecidos';
            $period = count((array)($m['days'] ?? [])) > 1 ? ' do período' : ' do dia';
            $distanceText = ((float)($m['distance_km'] ?? 0) > 0 || ((int)($m['closed_routes'] ?? 0) > 0 && (int)($m['incomplete_routes'] ?? 0) === 0))
                ? self::km((float)($m['distance_km'] ?? 0))
                : 'quilometragem ainda por apurar';
            $costText = (int)($m['costed_routes'] ?? 0) > 0
                ? self::eur((float)($m['energy_cost_eur'] ?? 0)) . ' de combustível/energia estimado e ' . self::eur((float)($m['toll_cost_eur'] ?? 0)) . ' de portagens'
                : 'custos de combustível/energia e portagens ainda por calcular';
            $paragraphs[] = $prefix . $period . ': ' . $distanceText . ', ' . $costText . '.';
        }

        $highlights = [];
        foreach (array_slice($gains, 0, 3) as $gain) $highlights[] = ['label'=>(string)$gain['location'],'value'=>self::signed((float)$gain['delta']) . ' frentes','detail'=>self::itemDetail($gain)];
        return [
            'eyebrow'=>'O teu fecho do dia',
            'headline'=>self::merchHeadline($facts, count($alerts)),
            'paragraphs'=>$paragraphs,
            'highlights'=>$highlights,
            'alerts'=>$alerts,
            'show_alerts'=>!empty($alerts),
            'alert_count'=>count($alerts),
        ];
    }

    private static function clientHeadline(array $facts, int $exceptionCount): string {
        $v = $facts['visits'];
        $f = $facts['fronts'];
        $parts = [];
        if ($v['planned'] > 0) $parts[] = self::pct($v['execution_rate']) . ' execução';
        if (abs((float)$f['net']) > 0.0001) $parts[] = self::signed((float)$f['net']) . ' frentes';
        if ($exceptionCount > 0) $parts[] = $exceptionCount . ' exceção(ões)';
        return $parts ? implode(' · ', $parts) : 'Resumo comercial disponível';
    }

    private static function merchHeadline(array $facts, int $alertCount): string {
        $v = $facts['visits'];
        $f = $facts['fronts'];
        $parts = [];
        if ($v['planned'] > 0) $parts[] = $v['completed'] . '/' . $v['planned'] . ' visitas';
        if (abs((float)$f['net']) > 0.0001) $parts[] = self::signed((float)$f['net']) . ' frentes';
        if ($alertCount > 0) $parts[] = $alertCount . ' alerta(s)';
        return $parts ? implode(' · ', $parts) : 'Fecho diário disponível';
    }

    private static function itemDetail(array $entry): string {
        $items = [];
        foreach ((array)($entry['items'] ?? []) as $item) {
            if (!is_array($item)) continue;
            $label = trim((string)($item['label'] ?? ''));
            if ($label === '') continue;
            $items[] = $label . ' ' . self::signed((float)($item['delta'] ?? 0));
        }
        return implode(' · ', array_slice($items, 0, 3));
    }

    public static function km(float $value): string {
        return number_format($value, 1, ',', '.') . ' km';
    }

    public static function eur(float $value): string {
        return number_format($value, 2, ',', '.') . ' €';
    }

    private static function numeric($value): ?float {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return (float)$value;
        $text = str_replace([' ', '%'], '', (string)$value);
        if (substr_count($text, ',') === 1 && substr_count($text, '.') === 0) $text = str_replace(',', '.', $text);
        return is_numeric($text) ? (float)$text : null;
    }

    public static function signed(float $value): string {
        return ($value > 0 ? '+' : '') . self::plain($value);
    }

    public static function plain(float $value): string {
        return number_format($value, abs($value - round($value)) < 0.0001 ? 0 : 1, ',', '.');
    }

    public static function pct(float $value): string {
        return number_format($value, 1, ',', '.') . '%';
    }
}
