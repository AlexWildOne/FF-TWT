<?php
namespace RoutesPro\Stores;

if (!defined('ABSPATH')) exit;

final class StoreProfileCard {
    public static function render($snapshot): string {
        if (is_wp_error($snapshot) || !is_array($snapshot)) return '';
        $location = is_array($snapshot['location'] ?? null) ? $snapshot['location'] : [];
        $profile = is_array($snapshot['profile'] ?? null) ? $snapshot['profile'] : [];
        $lastVisit = is_array($snapshot['last_visit'] ?? null) ? $snapshot['last_visit'] : [];
        $scope = is_array($snapshot['scope'] ?? null) ? $snapshot['scope'] : [];
        $assortment = is_array($snapshot['assortment'] ?? null) ? $snapshot['assortment'] : [];
        $canEdit = !empty($snapshot['permissions']['can_edit']);
        $locationId = absint($location['id'] ?? 0);
        if (!$locationId) return '';

        $source = trim((string)($location['source'] ?? '')) ?: 'base';
        $sourceLabel = self::sourceLabel($source);
        $placeBadge = trim((string)($location['place_id'] ?? '')) !== '' ? 'Google verificado' : $sourceLabel;
        $pendingStatus = sanitize_key((string)($profile['pending_status'] ?? 'none'));
        $pendingLabel = self::pendingLabel($pendingStatus);
        $pendingClass = $pendingStatus === 'open' ? 'is-warning' : ($pendingStatus === 'resolved' ? 'is-success' : 'is-neutral');
        $complete = !empty($snapshot['profile_complete']);
        $lastLabel = !empty($lastVisit['exists']) ? self::visitDate((string)($lastVisit['submitted_at'] ?? '')) : 'Sem visita anterior';
        $lastStatus = self::statusLabel((string)($lastVisit['status'] ?? 'never'));
        $endpoint = rest_url('routespro/v1/locations/' . $locationId . '/profile');
        $nonce = wp_create_nonce('wp_rest');

        $responsible = trim((string)($profile['responsible_name'] ?? ''));
        $storeFormat = trim((string)($profile['store_format'] ?? ''));
        $assortmentProfile = (string)($profile['assortment_profile'] ?? 'general');
        $assortmentLabel = trim((string)($assortment['selected_label'] ?? '')) ?: ($assortmentProfile === 'general' ? 'Geral' : strtoupper($assortmentProfile));
        $assortmentOptions = is_array($assortment['options'] ?? null) ? $assortment['options'] : [['value'=>'general','label'=>'Geral, Cardex completo']];
        $cardexName = trim((string)($assortment['cardex_name'] ?? ''));
        $cardexReviewTypes = array_values((array)($assortment['review_types'] ?? []));
        $phone = trim((string)($profile['operational_phone'] ?? ''));
        $email = trim((string)($profile['operational_email'] ?? ''));
        $pending = trim((string)($profile['pending_actions'] ?? ''));
        $notes = trim((string)($profile['store_notes'] ?? ''));

        ob_start(); ?>
        <details class="routespro-store-profile" data-rp-store-profile
            data-endpoint="<?php echo esc_url($endpoint); ?>"
            data-nonce="<?php echo esc_attr($nonce); ?>"
            data-location-id="<?php echo esc_attr((string)$locationId); ?>"
            data-project-id="<?php echo esc_attr((string)absint($scope['project_id'] ?? 0)); ?>"
            data-route-id="<?php echo esc_attr((string)absint($scope['route_id'] ?? 0)); ?>"
            data-revision="<?php echo esc_attr((string)absint($profile['revision_no'] ?? 0)); ?>" open>
            <summary class="routespro-store-profile-summary">
                <span class="routespro-store-profile-summary-main">
                    <span class="routespro-store-profile-kicker">Ficha da loja</span>
                    <strong><?php echo esc_html((string)($location['name'] ?? 'Loja')); ?></strong>
                    <small><?php echo esc_html(self::locationLine($location)); ?></small>
                </span>
                <span class="routespro-store-profile-chips">
                    <span class="routespro-store-chip is-source"><?php echo esc_html($placeBadge); ?></span>
                    <span class="routespro-store-chip is-neutral" data-rp-store-chip="assortment"><?php echo esc_html('Tipo ' . $assortmentLabel); ?></span>
                    <span class="routespro-store-chip <?php echo esc_attr($pendingClass); ?>" data-rp-store-chip="pending"><?php echo esc_html($pendingLabel); ?></span>
                    <span class="routespro-store-chip <?php echo $complete ? 'is-success' : 'is-warning'; ?>" data-rp-store-chip="complete"><?php echo esc_html($complete ? 'Perfil completo' : 'Faltam contactos'); ?></span>
                </span>
            </summary>

            <div class="routespro-store-profile-body">
                <section class="routespro-store-profile-section routespro-store-profile-status">
                    <div>
                        <span class="routespro-store-profile-label">Última visita</span>
                        <strong><?php echo esc_html($lastLabel); ?></strong>
                        <?php if (!empty($lastVisit['user_name'])): ?><small><?php echo esc_html((string)$lastVisit['user_name']); ?></small><?php endif; ?>
                    </div>
                    <div>
                        <span class="routespro-store-profile-label">Estado</span>
                        <strong><?php echo esc_html($lastStatus); ?></strong>
                    </div>
                    <div>
                        <span class="routespro-store-profile-label">Origem dos dados base</span>
                        <strong><?php echo esc_html($sourceLabel); ?></strong>
                        <small>Informação protegida, apenas leitura</small>
                    </div>
                </section>

                <section class="routespro-store-profile-section">
                    <div class="routespro-store-profile-section-head">
                        <div><span class="routespro-store-profile-kicker">Dados de origem</span><h4>Informação da localização</h4></div>
                        <span class="routespro-store-readonly-badge">Só leitura</span>
                    </div>
                    <div class="routespro-store-profile-grid is-readonly">
                        <?php self::readonlyItem('Morada', (string)($location['address'] ?? '')); ?>
                        <?php self::readonlyItem('Código postal', (string)($location['postal_code'] ?? '')); ?>
                        <?php self::readonlyItem('Localidade', (string)($location['city'] ?? '')); ?>
                        <?php self::readonlyItem('Telefone base', (string)($location['phone'] ?? '')); ?>
                        <?php self::readonlyItem('Email base', (string)($location['email'] ?? '')); ?>
                        <?php self::readonlyItem('Website', (string)($location['website'] ?? '')); ?>
                        <?php self::readonlyItem('Categoria', self::categoryLine($location)); ?>
                        <?php self::readonlyItem('Contacto base', (string)($location['contact_person'] ?? '')); ?>
                    </div>
                </section>

                <section class="routespro-store-profile-section is-operational">
                    <div class="routespro-store-profile-section-head">
                        <div><span class="routespro-store-profile-kicker">Operação</span><h4>Contacto, tipologia, pendentes e notas</h4></div>
                        <?php if ($canEdit): ?>
                            <button type="button" class="routespro-store-profile-edit" data-rp-store-edit><?php echo absint($profile['revision_no'] ?? 0) > 0 ? 'Editar dados' : 'Completar ficha'; ?></button>
                        <?php endif; ?>
                    </div>

                    <div class="routespro-store-profile-view" data-rp-store-view>
                        <div class="routespro-store-profile-grid">
                            <?php self::displayItem('Responsável', $responsible, 'responsible_name'); ?>
                            <?php self::displayItem('Formato da loja', $storeFormat, 'store_format'); ?>
                            <?php self::displayItem('Tipo Cardex / Perfil', $assortmentLabel, 'assortment_profile'); ?>
                            <?php self::displayItem('Telefone operacional', $phone, 'operational_phone'); ?>
                            <?php self::displayItem('Email operacional', $email, 'operational_email'); ?>
                            <?php self::displayItem('Estado de pendentes', $pendingLabel, 'pending_status'); ?>
                        </div>
                        <?php if ($cardexReviewTypes): ?>
                        <p class="routespro-store-profile-updated" role="status">Perfis do Cardex a confirmar com a marca: <?php echo esc_html(implode(', ', $cardexReviewTypes)); ?>. Não foram convertidos automaticamente para P1–P5 nem associados a esta loja.</p>
                        <?php endif; ?>
                        <div class="routespro-store-profile-textblocks">
                            <?php self::displayText('Pendentes', $pending, 'pending_actions'); ?>
                            <?php self::displayText('Notas permanentes', $notes, 'store_notes'); ?>
                        </div>
                        <?php if (!empty($profile['updated_at'])): ?>
                            <p class="routespro-store-profile-updated" data-rp-store-updated>Atualizado em <?php echo esc_html(mysql2date('d/m/Y H:i', (string)$profile['updated_at'])); ?><?php echo !empty($profile['updated_by_name']) ? ' por ' . esc_html((string)$profile['updated_by_name']) : ''; ?></p>
                        <?php else: ?>
                            <p class="routespro-store-profile-updated" data-rp-store-updated>Ficha operacional ainda não preenchida.</p>
                        <?php endif; ?>
                    </div>

                    <?php if ($canEdit): ?>
                    <div class="routespro-store-profile-editor" data-rp-store-editor hidden>
                        <div class="routespro-store-profile-editor-grid">
                            <label>Responsável<input type="text" value="<?php echo esc_attr($responsible); ?>" data-rp-store-field="responsible_name" autocomplete="name"></label>
                            <label>Formato da loja<input type="text" value="<?php echo esc_attr($storeFormat); ?>" data-rp-store-field="store_format" placeholder="Ex.: CNT, MDL, CBD, Hiper, Super"></label>
                            <label>Tipo Cardex / Perfil<select data-rp-store-field="assortment_profile">
                                <?php foreach ($assortmentOptions as $option): $value=(string)($option['value']??'general'); $label=(string)($option['label']??$value); ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($assortmentProfile, $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select><?php if ($cardexName !== ''): ?><small>Cardex ativo: <?php echo esc_html($cardexName); ?><?php echo !empty($assortment['cumulative']) ? ' · P1-Pn cumulativo' : ''; ?><?php echo !empty($assortment['store_override']) ? ' · ajuste específico da loja tem prioridade' : ''; ?></small><?php endif; ?></label>
                            <label>Telefone operacional<input type="tel" value="<?php echo esc_attr($phone); ?>" data-rp-store-field="operational_phone" autocomplete="tel"></label>
                            <label>Email operacional<input type="email" value="<?php echo esc_attr($email); ?>" data-rp-store-field="operational_email" autocomplete="email"></label>
                            <label>Estado de pendentes<select data-rp-store-field="pending_status">
                                <option value="none" <?php selected($pendingStatus, 'none'); ?>>Sem pendentes</option>
                                <option value="open" <?php selected($pendingStatus, 'open'); ?>>Com pendentes</option>
                                <option value="resolved" <?php selected($pendingStatus, 'resolved'); ?>>Resolvidos</option>
                            </select></label>
                        </div>
                        <label>Pendentes<textarea rows="3" data-rp-store-field="pending_actions" placeholder="O que ficou por resolver na loja?"><?php echo esc_textarea($pending); ?></textarea></label>
                        <label>Notas permanentes<textarea rows="3" data-rp-store-field="store_notes" placeholder="Informação útil para visitas futuras."><?php echo esc_textarea($notes); ?></textarea></label>
                        <div class="routespro-store-profile-actions">
                            <button type="button" class="routespro-store-profile-save" data-rp-store-save>Guardar ficha</button>
                            <button type="button" class="routespro-store-profile-cancel" data-rp-store-cancel>Cancelar</button>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="routespro-store-profile-message" data-rp-store-message aria-live="polite"></div>
                </section>
            </div>
        </details>
        <?php
        return (string)ob_get_clean();
    }

    private static function readonlyItem(string $label, string $value): void {
        $value = trim($value);
        echo '<div class="routespro-store-profile-field"><span>' . esc_html($label) . '</span><strong>' . esc_html($value !== '' ? $value : 'Não disponível') . '</strong></div>';
    }

    private static function displayItem(string $label, string $value, string $key): void {
        echo '<div class="routespro-store-profile-field"><span>' . esc_html($label) . '</span><strong data-rp-store-display="' . esc_attr($key) . '">' . esc_html(trim($value) !== '' ? $value : 'Não preenchido') . '</strong></div>';
    }

    private static function displayText(string $label, string $value, string $key): void {
        echo '<div class="routespro-store-profile-text"><span>' . esc_html($label) . '</span><div data-rp-store-display="' . esc_attr($key) . '">' . nl2br(esc_html(trim($value) !== '' ? $value : 'Sem informação.')) . '</div></div>';
    }

    private static function sourceLabel(string $source): string {
        $source = strtolower(trim($source));
        if (strpos($source, 'google') !== false || $source === 'places') return 'Google';
        if ($source === 'csv' || strpos($source, 'import') !== false) return 'Importação';
        if ($source === 'manual') return 'Base Comercial';
        return $source !== '' ? ucfirst($source) : 'Base Comercial';
    }

    private static function pendingLabel(string $status): string {
        if ($status === 'open') return 'Com pendentes';
        if ($status === 'resolved') return 'Pendentes resolvidos';
        return 'Sem pendentes';
    }

    private static function statusLabel(string $status): string {
        $status = sanitize_key($status);
        $map = [
            'never' => 'Sem visita anterior',
            'completed' => 'Concluída',
            'complete' => 'Concluída',
            'executed' => 'Executada',
            'done' => 'Concluída',
            'submitted' => 'Reporte submetido',
            'in_progress' => 'Em curso',
            'pending' => 'Pendente',
            'failed' => 'Não concluída',
            'cancelled' => 'Cancelada',
        ];
        return $map[$status] ?? ($status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Sem estado');
    }

    private static function visitDate(string $date): string {
        if ($date === '') return 'Visita anterior';
        return mysql2date('d/m/Y H:i', $date);
    }

    private static function locationLine(array $location): string {
        $parts = array_filter([
            trim((string)($location['city'] ?? '')),
            trim((string)($location['postal_code'] ?? '')),
        ]);
        return $parts ? implode(' · ', $parts) : trim((string)($location['address'] ?? ''));
    }

    private static function categoryLine(array $location): string {
        return implode(' / ', array_filter([
            trim((string)($location['category_name'] ?? '')),
            trim((string)($location['subcategory_name'] ?? '')),
        ]));
    }
}
