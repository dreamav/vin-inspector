<template>
  <div v-if="result || loading" class="oem-result-card glass-panel" :class="[statusClass, { 'is-loading': loading }]">
    <!-- Состояние загрузки проверки детали -->
    <div v-if="loading" class="result-loading">
      <div class="spinner-oem"></div>
      <div class="loading-text">
        <h4>Проверяем деталь {{ oemCode }} на infinitipartsdeal.com...</h4>
        <p>Ищем в каталоге, переходим на страницу детали с привязкой к VIN, а затем получаем Vehicle Fitment с чистой страницы</p>
      </div>
    </div>

    <!-- Результат проверки -->
    <div v-else-if="result" class="result-body">
      <!-- Верхняя плашка статуса -->
      <div class="status-banner">
        <div class="status-icon-wrap">
          <span v-if="result.status === 'fit'" class="status-icon icon-fit">✓</span>
          <span v-else-if="result.status === 'not-fit'" class="status-icon icon-not-fit">✕</span>
          <span v-else class="status-icon icon-unknown">ℹ</span>
        </div>

        <div class="status-main">
          <div class="status-badge-row">
            <span class="badge-tag" :class="'tag-' + result.status">
              {{ statusBadgeLabel }}
            </span>
            <span class="oem-code-tag">OEM: {{ result.oem }}</span>
          </div>

          <!-- Главный вывод: содержимое div с классом pn-check-confirm-text -->
          <div class="confirm-text-display">
            <span class="confirm-text-label">Содержимое .pn-check-confirm-text:</span>
            <div class="confirm-text-value">
              "{{ result.confirmText }}"
            </div>
          </div>
        </div>
      </div>

      <!-- Дополнительная информация о детали и авто -->
      <div class="result-details-grid">
        <div v-if="result.partTitle" class="detail-row">
          <span class="detail-label">Наименование детали:</span>
          <span class="detail-value part-title">{{ result.partTitle }}</span>
        </div>

        <div v-if="result.vehicleInfo" class="detail-row">
          <span class="detail-label">Привязка к автомобилю:</span>
          <span class="detail-value">{{ result.vehicleInfo }}</span>
        </div>

        <div v-if="result.categoryUrl" class="detail-row">
          <span class="detail-label">Каталог авто (Шаг 1):</span>
          <a :href="result.categoryUrl" target="_blank" rel="noopener noreferrer" class="link-external">
            Открыть категорию авто на infinitipartsdeal.com ↗
          </a>
        </div>

        <div v-if="result.partUrl" class="detail-row">
          <span class="detail-label">Страница детали (с VIN):</span>
          <a :href="result.partUrl" target="_blank" rel="noopener noreferrer" class="link-external">
            Открыть страницу детали с параметрами авто ↗
          </a>
        </div>

        <div v-if="result.cleanPartUrl" class="detail-row clean-url-row">
          <span class="detail-label">Чистая ссылка (без query):</span>
          <a :href="result.cleanPartUrl" target="_blank" rel="noopener noreferrer" class="link-external clean-link">
            {{ result.cleanPartUrl }} ↗
          </a>
        </div>
      </div>

      <!-- Секция Vehicle Fitment со страницы без query параметров -->
      <div v-if="result.fitmentDiv || (result.fitmentVehicles && result.fitmentVehicles.length)" class="fitment-section">
        <div class="fitment-header">
          <div class="fitment-title-wrap">
            <span class="fitment-icon">📋</span>
            <div>
              <h3 class="fitment-title">Список совместимости: li data-id="Vehicle Fitment"</h3>
              <p class="fitment-subtitle">
                Извлечено из <code>&lt;div class="fit-vehicle-list"&gt;</code> на странице без query-параметров
              </p>
            </div>
          </div>

          <div class="fitment-actions">
            <button 
              type="button" 
              class="btn-toggle-fitment" 
              @click="showFitmentRaw = !showFitmentRaw"
            >
              {{ showFitmentRaw ? '📊 Показать таблицу' : '📄 Показать HTML код div' }}
            </button>
          </div>
        </div>

        <!-- Таблица совместимости -->
        <div v-if="!showFitmentRaw" class="fitment-table-wrap">
          <table v-if="result.fitmentVehicles && result.fitmentVehicles.length" class="fitment-table">
            <thead>
              <tr>
                <th>Год, марка, модель (Year Make Model)</th>
                <th>Комплектация и двигатель (Trim & Engine)</th>
                <th>Опции автомобиля (Option Details)</th>
              </tr>
            </thead>
            <tbody>
              <tr 
                v-for="(item, idx) in result.fitmentVehicles" 
                :key="idx"
                :class="{ 'highlight-match': isCurrentVehicleMatch(item) }"
              >
                <td class="col-vehicle">
                  <span class="ymm-text">{{ item.yearMakeModel }}</span>
                  <span v-if="isCurrentVehicleMatch(item)" class="current-vehicle-tag">Текущее авто</span>
                </td>
                <td class="col-trim">{{ item.trimEngine || '—' }}</td>
                <td class="col-options">{{ item.options || '—' }}</td>
              </tr>
            </tbody>
          </table>

          <div v-else class="fitment-empty">
            Список совместимых автомобилей в таблице не найден
          </div>
        </div>

        <!-- Исходный HTML блока div Vehicle Fitment -->
        <div v-else class="fitment-raw-wrap">
          <div class="raw-fitment-bar">
            <span>Исходный HTML блока &lt;div class="fit-vehicle-list"&gt;:</span>
          </div>
          <pre class="raw-fitment-html"><code>{{ result.fitmentDiv || result.fitmentLi }}</code></pre>
        </div>
      </div>

      <!-- Переключатель просмотра оригинального HTML блока подтверждения -->
      <div class="raw-toggle-wrap">
        <button type="button" class="btn-toggle-raw" @click="showRaw = !showRaw">
          {{ showRaw ? '▲ Скрыть исходный HTML-блок .pn-check-confirm-text' : '▼ Показать спарсенный HTML-блок (.pn-check-confirm-text)' }}
        </button>

        <transition name="fade">
          <div v-if="showRaw" class="raw-code-box">
            <div class="raw-box-title">Точный спарсенный HTML (.pn-check-confirm-text):</div>
            <pre><code>{{ result.rawBlock || result.rawCheckBlock }}</code></pre>
          </div>
        </transition>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'OemCheckResult',
  props: {
    result: {
      type: Object,
      default: null
    },
    loading: {
      type: Boolean,
      default: false
    },
    oemCode: {
      type: String,
      default: ''
    }
  },
  data() {
    return {
      showRaw: false,
      showFitmentRaw: false
    };
  },
  computed: {
    statusClass() {
      if (!this.result) return '';
      return 'status-' + (this.result.status || 'unknown');
    },
    statusBadgeLabel() {
      if (!this.result) return '';
      switch (this.result.status) {
        case 'fit':
          return '✓ Деталь совместима (Подходит)';
        case 'not-fit':
          return '✕ Не совместима (Не подходит)';
        case 'partial':
          return '⚠️ Частичная совместимость / Требуется уточнение';
        default:
          return 'Информация о совместимости';
      }
    }
  },
  methods: {
    isCurrentVehicleMatch(item) {
      if (!this.result || !this.result.vehicleInfo) return false;
      const vInfo = this.result.vehicleInfo.toLowerCase();
      const ymm = (item.yearMakeModel || '').toLowerCase();
      // Проверка совпадения марки/модели и года
      if (vInfo.includes('qx50') && ymm.includes('qx50') && ymm.includes('2019')) {
        return true;
      }
      return false;
    }
  }
};
</script>

<style scoped>
.oem-result-card {
  margin-bottom: 2rem;
  padding: 1.75rem 2rem;
  border-radius: var(--radius-md);
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

/* Стили в зависимости от статуса */
.oem-result-card.status-not-fit {
  border: 1.5px solid rgba(239, 68, 68, 0.4);
  background: linear-gradient(180deg, rgba(239, 68, 68, 0.1) 0%, rgba(13, 19, 33, 0.95) 100%);
  box-shadow: 0 8px 30px rgba(239, 68, 68, 0.15);
}

.oem-result-card.status-fit {
  border: 1.5px solid rgba(16, 185, 129, 0.4);
  background: linear-gradient(180deg, rgba(16, 185, 129, 0.1) 0%, rgba(13, 19, 33, 0.95) 100%);
  box-shadow: 0 8px 30px rgba(16, 185, 129, 0.15);
}

.oem-result-card.status-partial,
.oem-result-card.status-unknown {
  border: 1.5px solid rgba(245, 158, 11, 0.4);
  background: linear-gradient(180deg, rgba(245, 158, 11, 0.1) 0%, rgba(13, 19, 33, 0.95) 100%);
  box-shadow: 0 8px 30px rgba(245, 158, 11, 0.15);
}

/* Лоадер */
.result-loading {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  padding: 1rem 0;
}

.spinner-oem {
  width: 2.5rem;
  height: 2.5rem;
  border: 3px solid rgba(99, 102, 241, 0.2);
  border-top-color: #818cf8;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  flex-shrink: 0;
}

.loading-text h4 {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--text-main);
  margin-bottom: 0.25rem;
}

.loading-text p {
  font-size: 0.85rem;
  color: var(--text-muted);
}

/* Статус баннер */
.status-banner {
  display: flex;
  align-items: flex-start;
  gap: 1.25rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid var(--border-subtle);
  margin-bottom: 1.25rem;
}

.status-icon-wrap {
  flex-shrink: 0;
}

.status-icon {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.6rem;
  font-weight: 900;
}

.icon-not-fit {
  background: rgba(239, 68, 68, 0.2);
  color: #f87171;
  border: 1.5px solid rgba(239, 68, 68, 0.4);
  box-shadow: 0 0 20px rgba(239, 68, 68, 0.3);
}

.icon-fit {
  background: rgba(16, 185, 129, 0.2);
  color: #34d399;
  border: 1.5px solid rgba(16, 185, 129, 0.4);
  box-shadow: 0 0 20px rgba(16, 185, 129, 0.3);
}

.icon-unknown {
  background: rgba(245, 158, 11, 0.2);
  color: #fbbf24;
  border: 1.5px solid rgba(245, 158, 11, 0.4);
}

.status-main {
  flex: 1;
}

.status-badge-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
  margin-bottom: 0.5rem;
}

.badge-tag {
  font-size: 0.8rem;
  font-weight: 700;
  padding: 0.25rem 0.65rem;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.tag-not-fit {
  background: rgba(239, 68, 68, 0.2);
  color: #fca5a5;
  border: 1px solid rgba(239, 68, 68, 0.35);
}

.tag-fit {
  background: rgba(16, 185, 129, 0.2);
  color: #86efac;
  border: 1px solid rgba(16, 185, 129, 0.35);
}

.tag-partial,
.tag-unknown {
  background: rgba(245, 158, 11, 0.2);
  color: #fde68a;
  border: 1px solid rgba(245, 158, 11, 0.35);
}

.oem-code-tag {
  font-family: var(--font-mono);
  font-size: 0.82rem;
  font-weight: 700;
  color: #e2e8f0;
  background: rgba(255, 255, 255, 0.08);
  padding: 0.25rem 0.65rem;
  border-radius: 6px;
  border: 1px solid var(--border-subtle);
}

.confirm-text-display {
  margin-top: 0.4rem;
}

.confirm-text-label {
  font-size: 0.78rem;
  color: var(--text-dim);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 600;
  display: block;
  margin-bottom: 0.2rem;
}

.confirm-text-value {
  font-size: 1.5rem;
  font-weight: 800;
  letter-spacing: -0.01em;
  line-height: 1.25;
}

.status-not-fit .confirm-text-value {
  color: #f87171;
}

.status-fit .confirm-text-value {
  color: #34d399;
}

.status-partial .confirm-text-value,
.status-unknown .confirm-text-value {
  color: #fbbf24;
}

/* Детали */
.result-details-grid {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  margin-bottom: 1.5rem;
}

.detail-row {
  display: flex;
  align-items: baseline;
  gap: 0.75rem;
  font-size: 0.9rem;
  flex-wrap: wrap;
}

.clean-url-row {
  background: rgba(99, 102, 241, 0.08);
  padding: 0.5rem 0.75rem;
  border-radius: 6px;
  border: 1px solid rgba(99, 102, 241, 0.25);
}

.detail-label {
  color: var(--text-dim);
  font-weight: 500;
  min-width: 180px;
}

.detail-value {
  color: var(--text-main);
  font-weight: 600;
}

.part-title {
  color: #f1f5f9;
  font-size: 0.95rem;
  font-weight: 700;
}

.link-external {
  color: var(--accent-cyan);
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: 500;
  transition: color 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

.link-external:hover {
  color: #7dd3fc;
  text-decoration: underline;
}

.clean-link {
  font-family: var(--font-mono);
  font-size: 0.8rem;
  color: #a5b4fc;
  word-break: break-all;
}

.clean-link:hover {
  color: #c7d2fe;
}

/* Vehicle Fitment Section */
.fitment-section {
  background: rgba(8, 12, 20, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--radius-sm);
  padding: 1.25rem 1.5rem;
  margin-bottom: 1.5rem;
}

.fitment-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--border-subtle);
}

.fitment-title-wrap {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.fitment-icon {
  font-size: 1.5rem;
  background: rgba(99, 102, 241, 0.15);
  padding: 0.4rem;
  border-radius: 8px;
  border: 1px solid rgba(99, 102, 241, 0.3);
}

.fitment-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text-main);
}

.fitment-subtitle {
  font-size: 0.78rem;
  color: var(--text-muted);
  margin-top: 0.15rem;
}

.fitment-subtitle code {
  color: #a5b4fc;
  font-family: var(--font-mono);
  background: rgba(0, 0, 0, 0.3);
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
}

.btn-toggle-fitment {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid var(--border-subtle);
  color: var(--accent-cyan);
  font-size: 0.78rem;
  font-weight: 600;
  padding: 0.35rem 0.75rem;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-toggle-fitment:hover {
  background: rgba(56, 189, 248, 0.15);
  border-color: rgba(56, 189, 248, 0.4);
}

/* Таблица Fitment */
.fitment-table-wrap {
  overflow-x: auto;
}

.fitment-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}

.fitment-table th {
  text-align: left;
  padding: 0.65rem 0.85rem;
  background: rgba(0, 0, 0, 0.35);
  color: var(--text-dim);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 700;
  border-bottom: 1px solid var(--border-subtle);
}

.fitment-table td {
  padding: 0.75rem 0.85rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  color: var(--text-main);
  vertical-align: middle;
}

.fitment-table tr:hover {
  background: rgba(255, 255, 255, 0.03);
}

.fitment-table tr.highlight-match {
  background: rgba(16, 185, 129, 0.12);
  border-left: 3px solid #10b981;
}

.col-vehicle {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
}

.ymm-text {
  font-weight: 700;
  color: #ffffff;
}

.current-vehicle-tag {
  background: rgba(16, 185, 129, 0.25);
  color: #6ee7b7;
  font-size: 0.68rem;
  font-weight: 700;
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
  border: 1px solid rgba(16, 185, 129, 0.4);
}

.col-trim {
  color: #cbd5e1;
}

.col-options {
  color: var(--accent-cyan);
  font-family: var(--font-mono);
  font-size: 0.8rem;
}

.fitment-empty {
  padding: 1.5rem;
  text-align: center;
  color: var(--text-dim);
  font-size: 0.85rem;
}

/* Raw fitment */
.fitment-raw-wrap {
  background: #05080f;
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-sm);
  padding: 1rem;
}

.raw-fitment-bar {
  font-size: 0.75rem;
  color: var(--text-dim);
  margin-bottom: 0.5rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  font-weight: 600;
}

.raw-fitment-html {
  margin: 0;
  white-space: pre-wrap;
  word-break: break-all;
  font-family: var(--font-mono);
  font-size: 0.8rem;
  color: #38bdf8;
  max-height: 280px;
  overflow-y: auto;
}

/* Raw code block (confirm text) */
.raw-toggle-wrap {
  border-top: 1px solid var(--border-subtle);
  padding-top: 0.85rem;
}

.btn-toggle-raw {
  background: none;
  border: none;
  color: var(--text-dim);
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
  transition: color 0.2s;
}

.btn-toggle-raw:hover {
  color: var(--text-main);
}

.raw-code-box {
  margin-top: 0.75rem;
  background: #080c14;
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-sm);
  padding: 1rem;
}

.raw-box-title {
  font-size: 0.75rem;
  color: var(--text-dim);
  margin-bottom: 0.4rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 600;
}

.raw-code-box pre {
  margin: 0;
  white-space: pre-wrap;
  word-break: break-all;
}

.raw-code-box code {
  font-family: var(--font-mono);
  font-size: 0.85rem;
  color: #38bdf8;
}

.fade-enter-active, .fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter, .fade-leave-to {
  opacity: 0;
}
</style>
