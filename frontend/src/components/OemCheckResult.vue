<template>
  <div v-if="result || loading" class="oem-result-card glass-panel" :class="[statusClass, { 'is-loading': loading }]">
    <!-- Состояние загрузки проверки детали -->
    <div v-if="loading" class="result-loading">
      <div class="spinner-oem"></div>
      <div class="loading-text">
        <h4>Проверяем деталь {{ oemCode }} на {{ currentHost }}...</h4>
        <p>Ищем деталь в каталоге и проверяем совместимость с автомобилем...</p>
      </div>
    </div>

    <!-- Результат проверки -->
    <div v-else-if="result" class="result-body">
      <!-- Верхняя плашка статуса -->
      <div class="status-banner">
        <div class="status-icon-wrap">
          <span v-if="result.status === 'fit'" class="status-icon icon-fit">✓</span>
          <span v-else-if="result.status === 'not-fit' || result.status === 'not-in-catalog'" class="status-icon icon-not-fit">✕</span>
          <span v-else class="status-icon icon-unknown">ℹ</span>
        </div>

        <div class="status-main">
          <div class="status-badge-row">
            <span class="badge-tag" :class="'tag-' + result.status">
              {{ statusBadgeLabel }}
            </span>
            <span class="oem-code-tag">OEM: {{ result.oem }}</span>
          </div>

          <!-- Результат проверки совместимости -->
          <div class="confirm-text-display">
            <span class="confirm-text-label">Результат проверки:</span>
            <div class="confirm-text-value">
              "{{ result.confirmText }}"
            </div>
          </div>

          <!-- Отображение выбранной опции автомобиля (если уже была уточнена) -->
          <div v-if="result.selectedOption" class="selected-option-row">
            <span class="selected-option-badge">
              <span class="option-check-icon">✓</span>
              Учтена опция: <strong>{{ activeOptionLabel }}</strong>
            </span>
            <button v-if="hasOptions" type="button" class="btn-change-option" @click="openModal" title="Выбрать другую комплектацию">
              (изменить)
            </button>
          </div>
        </div>
      </div>

      <!-- Баннер при необходимости выбора опций автомобиля -->
      <div v-if="hasOptions && result.status === 'partial'" class="options-alert-banner">
        <div class="options-alert-left">
          <div class="options-alert-icon">⚙️</div>
          <div class="options-alert-text">
            <h4>Требуется уточнить комплектацию автомобиля</h4>
            <p>
              Данная деталь подходит не на все модификации. Выберите установленные опции (Select Vehicle Options), чтобы получить подтверждение совместимости.
            </p>
          </div>
        </div>
        <button type="button" class="btn-select-options" @click="openModal">
          <span class="btn-icon">⚙️</span>
          <span class="btn-text">Выбрать опции автомобиля</span>
          <span class="btn-subtext">(Select Vehicle Options)</span>
        </button>
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
          <span class="detail-label">Каталог автомобиля:</span>
          <a :href="result.categoryUrl" target="_blank" rel="noopener noreferrer" class="link-external">
            Открыть каталог {{ result.brand || '' }} на {{ currentHost }} ↗
          </a>
        </div>

        <div v-if="result.partUrl" class="detail-row">
          <span class="detail-label">Страница детали:</span>
          <a :href="result.partUrl" target="_blank" rel="noopener noreferrer" class="link-external">
            Открыть деталь на {{ currentHost }} ↗
          </a>
        </div>
      </div>

      <!-- Секция совместимых автомобилей -->
      <div v-if="result.fitmentVehicles && result.fitmentVehicles.length" class="fitment-section">
        <div class="fitment-header">
          <div class="fitment-title-wrap">
            <span class="fitment-icon">📋</span>
            <div>
              <h3 class="fitment-title">Список совместимых автомобилей</h3>
              <p class="fitment-subtitle">
                Модели и комплектации, на которые устанавливается данная деталь
              </p>
            </div>
          </div>
        </div>

        <!-- Таблица совместимости -->
        <div class="fitment-table-wrap">
          <table class="fitment-table">
            <thead>
              <tr>
                <th>Год, марка, модель</th>
                <th>Комплектация и двигатель</th>
                <th>Опции автомобиля</th>
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
        </div>
      </div>

      <!-- Секция предложений детали на Alshned Auto Parts -->
      <div v-if="result.alshned" class="alshned-section">
        <div class="alshned-header">
          <div class="alshned-title-wrap">
            <span class="alshned-badge-icon">🛒</span>
            <div>
              <div class="alshned-title-row">
                <h3 class="alshned-title">Предложения на Alshned Auto Parts</h3>
                <span v-if="result.alshned.count > 0" class="alshned-counter badge-found">
                  {{ result.alshned.count }} {{ result.alshned.count === 1 ? 'предложение' : 'предложений' }}
                </span>
                <span v-else class="alshned-counter badge-empty">
                  0 предложений
                </span>
              </div>
              <p class="alshned-subtitle">
                Поиск запчасти по номеру OEM на
                <a :href="result.alshned.searchUrl" target="_blank" rel="noopener noreferrer" class="link-alshned-site">
                  alshnedauto.com ↗
                </a>
              </p>
            </div>
          </div>

          <a 
            :href="result.alshned.searchUrl" 
            target="_blank" 
            rel="noopener noreferrer" 
            class="alshned-search-btn"
          >
            <span>Смотреть все на alshnedauto.com</span>
            <span class="btn-arrow">↗</span>
          </a>
        </div>

        <!-- Сетка карточек товаров Alshned Auto -->
        <div v-if="result.alshned.products && result.alshned.products.length" class="alshned-grid">
          <div 
            v-for="item in result.alshned.products" 
            :key="item.id" 
            class="alshned-card"
          >
            <div class="alshned-card-media">
              <a :href="item.url" target="_blank" rel="noopener noreferrer" class="alshned-img-link">
                <img 
                  v-if="item.image" 
                  :src="item.image" 
                  :alt="item.title"
                  class="alshned-img"
                  loading="lazy"
                />
                <div v-else class="alshned-no-img">
                  <span>📷</span>
                  <small>Нет фото</small>
                </div>
              </a>
              <span v-if="item.available" class="alshned-status in-stock">✓ В наличии</span>
              <span v-else class="alshned-status out-of-stock">Под заказ</span>
            </div>

            <div class="alshned-card-body">
              <div class="alshned-card-meta" v-if="item.vendor">
                <span class="alshned-vendor-tag">{{ item.vendor }}</span>
              </div>
              <h4 class="alshned-item-title">
                <a :href="item.url" target="_blank" rel="noopener noreferrer" :title="item.title">
                  {{ item.title }}
                </a>
              </h4>

              <div class="alshned-card-bottom">
                <div class="alshned-price-wrapper">
                  <span class="alshned-price-label">Цена:</span>
                  <span class="alshned-price-val">{{ item.priceFormatted || ('$' + item.price) }}</span>
                </div>

                <a 
                  :href="item.url" 
                  target="_blank" 
                  rel="noopener noreferrer" 
                  class="alshned-buy-btn"
                >
                  <span>Купить</span>
                  <span class="btn-arrow">↗</span>
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Если предложений не найдено -->
        <div v-else class="alshned-empty-box">
          <div class="empty-icon-wrap">🔍</div>
          <div class="empty-info">
            <h4 class="empty-title">На складе alshnedauto.com запчасть не найдена</h4>
            <p class="empty-desc">
              По артикулу <code>{{ result.alshned.query || result.oem }}</code> актуальных предложений не обнаружено.
            </p>
            <a :href="result.alshned.searchUrl" target="_blank" rel="noopener noreferrer" class="empty-link">
              Проверить результаты поиска на alshnedauto.com ↗
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Модальное окно выбора опций автомобиля (Select Vehicle Options Popup) -->
    <transition name="modal-fade">
      <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
        <div class="modal-card glass-panel" role="dialog" aria-modal="true">
          <div class="modal-header">
            <div class="modal-title-wrap">
              <h3 class="modal-title">Уточнение комплектации автомобиля</h3>
              <span class="modal-subtitle">Select Vehicle Options • {{ result.brand || '' }}</span>
            </div>
            <button type="button" class="modal-close-btn" @click="closeModal" title="Закрыть">✕</button>
          </div>

          <div class="modal-body">
            <div class="modal-vehicle-badge" v-if="vehicleInfoText">
              <span class="badge-icon">🚘</span>
              <div class="badge-content">
                <div class="badge-title">{{ vehicleInfoText }}</div>
                <div class="badge-subtitle" v-if="result.oem">
                  Деталь: <span class="badge-oem">{{ result.oem }}</span>
                  <span v-if="result.partTitle"> • {{ result.partTitle }}</span>
                </div>
              </div>
            </div>

            <div class="modal-instruction">
              Выберите параметры модификации, установленные на автомобиле, и нажмите <strong>Check Fit</strong>:
            </div>

            <!-- Списки опций по группам -->
            <div 
              v-for="(group, gIdx) in displayOptions" 
              :key="gIdx" 
              class="option-group-box"
            >
              <div class="group-title-row">
                <span class="group-name">{{ group.title || 'Параметры комплектации' }}</span>
                <span v-if="group.typeCode" class="group-code-tag">{{ group.typeCode }}</span>
              </div>

              <div class="options-grid">
                <div
                  v-for="(item, iIdx) in group.optionItemList"
                  :key="iIdx"
                  class="option-pill-card"
                  :class="{ active: selectedOptions[group.title] === item.code }"
                  @click="selectOption(group.title, item.code)"
                >
                  <div class="custom-radio">
                    <div class="radio-dot" v-if="selectedOptions[group.title] === item.code"></div>
                  </div>
                  <div class="option-details">
                    <span class="option-title">{{ item.text }}</span>
                    <span v-if="item.extraCode" class="option-code-pill">{{ item.extraCode }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn-modal-cancel" @click="closeModal">
              Отмена
            </button>
            <button 
              type="button" 
              class="btn-modal-submit" 
              :disabled="!isOptionSelected || loading"
              @click="handleCheckFit"
            >
              <span v-if="loading" class="spinner-small"></span>
              <span v-else>Проверить совместимость</span>
              <span class="btn-subtext">(Check Fit)</span>
              <span v-if="!loading" class="btn-arrow">→</span>
            </button>
          </div>
        </div>
      </div>
    </transition>
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
    },
    vehicle: {
      type: Object,
      default: null
    },
    catalogHost: {
      type: String,
      default: ''
    }
  },
  data() {
    return {
      showModal: false,
      selectedOptions: {},
      cachedOptions: []
    };
  },
  watch: {
    result: {
      immediate: true,
      handler(newVal) {
        if (newVal) {
          if (newVal.options && newVal.options.length) {
            this.cachedOptions = newVal.options;
          }
          const groups = this.displayOptions;
          if (groups && groups.length) {
            const nextSelected = { ...this.selectedOptions };
            groups.forEach(g => {
              if (newVal.selectedOption) {
                const match = (g.optionItemList || []).find(it => it.code === newVal.selectedOption || newVal.selectedOption.includes(it.code));
                if (match) {
                  nextSelected[g.title] = match.code;
                  return;
                }
              }
              if (!nextSelected[g.title]) {
                if (g.selectValue) nextSelected[g.title] = g.selectValue;
                else if (g.defaultValue) nextSelected[g.title] = g.defaultValue;
                else if (g.optionItemList && g.optionItemList.length === 1) nextSelected[g.title] = g.optionItemList[0].code;
              }
            });
            this.selectedOptions = nextSelected;
          }
        }
      }
    }
  },
  computed: {
    displayOptions() {
      if (this.result && this.result.options && this.result.options.length) {
        return this.result.options;
      }
      return this.cachedOptions || [];
    },
    hasOptions() {
      return this.displayOptions && this.displayOptions.length > 0;
    },
    isOptionSelected() {
      if (!this.displayOptions.length) return false;
      return this.displayOptions.every(g => !!this.selectedOptions[g.title]);
    },
    activeOptionLabel() {
      if (!this.result || !this.result.selectedOption) return '';
      const code = this.result.selectedOption;
      for (const g of this.displayOptions) {
        for (const item of (g.optionItemList || [])) {
          if (item.code === code || code.includes(item.code)) {
            return `${item.text} ${item.extraCode ? '[' + item.extraCode + ']' : ''}`;
          }
        }
      }
      return code;
    },
    vehicleInfoText() {
      if (!this.result) return '';
      return this.result.vehicleInfo || (this.vehicle && this.vehicle.title) || this.result.vin || '';
    },
    currentHost() {
      if (this.result) {
        if (this.result.siteUrl) {
          try {
            return new URL(this.result.siteUrl).hostname;
          } catch (e) {
            return this.result.siteUrl.replace(/^https?:\/\//, '').replace(/\/.*$/, '');
          }
        }
        if (this.result.categoryUrl) {
          try {
            return new URL(this.result.categoryUrl).hostname;
          } catch (e) {}
        }
      }
      if (this.catalogHost) {
        return this.catalogHost;
      }
      if (this.vehicle && this.vehicle.siteUrl) {
        try {
          return new URL(this.vehicle.siteUrl).hostname;
        } catch (e) {
          return this.vehicle.siteUrl.replace(/^https?:\/\//, '').replace(/\/.*$/, '');
        }
      }
      return 'каталоге запчастей';
    },
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
        case 'not-in-catalog':
          return '✕ Не найдена в каталоге марки';
        case 'partial':
          return '⚠️ Частичная совместимость / Требуется уточнение';
        default:
          return 'Информация о совместимости';
      }
    }
  },
  methods: {
    openModal() {
      this.showModal = true;
    },
    closeModal() {
      this.showModal = false;
    },
    selectOption(groupTitle, code) {
      this.$set(this.selectedOptions, groupTitle, code);
    },
    handleCheckFit() {
      const vals = Object.values(this.selectedOptions).filter(Boolean);
      if (!vals.length) return;
      const optionParam = vals.join(';');
      this.showModal = false;
      this.$emit('check-fit-options', {
        option: optionParam,
        selectedOptions: { ...this.selectedOptions }
      });
    },
    isCurrentVehicleMatch(item) {
      const vInfo = (this.result && this.result.vehicleInfo ? this.result.vehicleInfo : (this.vehicle && this.vehicle.title ? this.vehicle.title : '')).toLowerCase();
      if (!vInfo) return false;
      const ymm = (item.yearMakeModel || '').toLowerCase();
      
      const words = vInfo.match(/[a-z0-9]{3,}/gi) || [];
      if (!words.length) return false;
      
      const matches = words.filter(word => ymm.includes(word.toLowerCase()));
      return matches.length >= Math.min(2, words.length);
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
.oem-result-card.status-not-fit,
.oem-result-card.status-not-in-catalog {
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

/* ========================================================
   Стили опций автомобиля и модального окна (Vehicle Options)
   ======================================================== */

.selected-option-row {
  margin-top: 0.6rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.selected-option-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  background: rgba(16, 185, 129, 0.15);
  border: 1px solid rgba(16, 185, 129, 0.35);
  color: #a7f3d0;
  padding: 0.3rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.82rem;
}

.selected-option-badge strong {
  color: #34d399;
}

.option-check-icon {
  font-weight: 800;
  color: #10b981;
}

.btn-change-option {
  background: none;
  border: none;
  color: var(--accent-cyan);
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
  text-decoration: underline;
  transition: color 0.2s;
}

.btn-change-option:hover {
  color: #ffffff;
}

/* Баннер с предложением выбрать опции при статусе partial */
.options-alert-banner {
  margin-top: 1.25rem;
  padding: 1.25rem 1.5rem;
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.18) 0%, rgba(217, 119, 6, 0.08) 100%);
  border: 1.5px solid rgba(245, 158, 11, 0.45);
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  flex-wrap: wrap;
  box-shadow: 0 4px 20px rgba(245, 158, 11, 0.12);
}

.options-alert-left {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex: 1;
  min-width: 280px;
}

.options-alert-icon {
  font-size: 2rem;
  line-height: 1;
  flex-shrink: 0;
}

.options-alert-text h4 {
  margin: 0 0 0.25rem 0;
  color: #fbbf24;
  font-size: 1rem;
  font-weight: 700;
}

.options-alert-text p {
  margin: 0;
  color: #d1d5db;
  font-size: 0.86rem;
  line-height: 1.4;
}

.btn-select-options {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 0.75rem 1.5rem;
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: #0b0f19;
  border: none;
  border-radius: var(--radius-sm);
  font-weight: 800;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all 0.25s ease;
  box-shadow: 0 4px 15px rgba(245, 158, 11, 0.35);
  white-space: nowrap;
}

.btn-select-options:hover {
  transform: translateY(-2px);
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
  box-shadow: 0 6px 20px rgba(245, 158, 11, 0.5);
}

.btn-select-options .btn-subtext {
  font-size: 0.72rem;
  font-weight: 600;
  opacity: 0.85;
}

/* ========================================================
   Модальное окно (Modal Dialog)
   ======================================================== */

.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(6px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
}

.modal-card {
  width: 100%;
  max-width: 620px;
  max-height: 90vh;
  background: #111827;
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: var(--radius-md);
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(255, 255, 255, 0.1);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  animation: modalPop 0.25s ease-out;
}

@keyframes modalPop {
  0% {
    transform: scale(0.95) translateY(10px);
    opacity: 0;
  }
  100% {
    transform: scale(1) translateY(0);
    opacity: 1;
  }
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-subtle);
  background: rgba(255, 255, 255, 0.02);
}

.modal-title-wrap {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.modal-title {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 700;
  color: #ffffff;
}

.modal-subtitle {
  font-size: 0.78rem;
  color: var(--text-dim);
  font-weight: 500;
}

.modal-close-btn {
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: var(--text-dim);
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 1rem;
  transition: all 0.2s;
}

.modal-close-btn:hover {
  background: rgba(239, 68, 68, 0.2);
  color: #ef4444;
  border-color: rgba(239, 68, 68, 0.4);
}

.modal-body {
  padding: 1.5rem;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.modal-vehicle-badge {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-sm);
  padding: 0.75rem 1rem;
}

.badge-icon {
  font-size: 1.5rem;
}

.badge-content {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.badge-title {
  font-weight: 700;
  color: #f3f4f6;
  font-size: 0.92rem;
}

.badge-subtitle {
  font-size: 0.8rem;
  color: var(--text-dim);
}

.badge-oem {
  color: var(--accent-cyan);
  font-family: var(--font-mono);
  font-weight: 700;
}

.modal-instruction {
  font-size: 0.88rem;
  color: #9ca3af;
  line-height: 1.4;
}

.option-group-box {
  background: rgba(0, 0, 0, 0.25);
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-sm);
  padding: 1rem;
}

.group-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.75rem;
}

.group-name {
  font-size: 0.92rem;
  font-weight: 700;
  color: #e5e7eb;
}

.group-tag {
  background: rgba(99, 102, 241, 0.2);
  color: #a5b4fc;
  font-size: 0.72rem;
  font-weight: 600;
  padding: 0.15rem 0.5rem;
  border-radius: 4px;
}

.options-grid {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.option-pill-card {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 0.8rem 1rem;
  background: rgba(255, 255, 255, 0.03);
  border: 1.5px solid rgba(255, 255, 255, 0.08);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: all 0.2s ease;
}

.option-pill-card:hover {
  background: rgba(255, 255, 255, 0.06);
  border-color: rgba(99, 102, 241, 0.4);
}

.option-pill-card.active {
  background: rgba(99, 102, 241, 0.15);
  border-color: #6366f1;
  box-shadow: 0 0 15px rgba(99, 102, 241, 0.25);
}

.custom-radio {
  width: 1.25rem;
  height: 1.25rem;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.2s;
}

.option-pill-card.active .custom-radio {
  border-color: #6366f1;
}

.radio-dot {
  width: 0.6rem;
  height: 0.6rem;
  background: #818cf8;
  border-radius: 50%;
}

.option-details {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  gap: 0.5rem;
}

.option-title {
  font-size: 0.88rem;
  color: #f3f4f6;
  font-weight: 500;
  line-height: 1.3;
}

.option-code-pill {
  background: rgba(255, 255, 255, 0.08);
  color: #cbd5e1;
  font-family: var(--font-mono);
  font-size: 0.72rem;
  font-weight: 700;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  white-space: nowrap;
}

.modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1.1rem 1.5rem;
  border-top: 1px solid var(--border-subtle);
  background: rgba(255, 255, 255, 0.02);
}

.btn-modal-cancel {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid var(--border-subtle);
  color: var(--text-dim);
  padding: 0.65rem 1.25rem;
  border-radius: var(--radius-sm);
  font-weight: 600;
  font-size: 0.88rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-modal-cancel:hover {
  background: rgba(255, 255, 255, 0.1);
  color: #ffffff;
}

.btn-modal-submit {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
  color: #ffffff;
  border: none;
  padding: 0.7rem 1.5rem;
  border-radius: var(--radius-sm);
  font-weight: 700;
  font-size: 0.92rem;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 4px 15px rgba(79, 70, 229, 0.35);
}

.btn-modal-submit:hover:not(:disabled) {
  background: linear-gradient(135deg, #6366f1 0%, #60a5fa 100%);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(79, 70, 229, 0.5);
}

.btn-modal-submit:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  box-shadow: none;
}

.btn-modal-submit .btn-subtext {
  font-size: 0.78rem;
  font-weight: 500;
  opacity: 0.85;
}

.spinner-small {
  width: 1.1rem;
  height: 1.1rem;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #ffffff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.modal-fade-enter-active, .modal-fade-leave-active {
  transition: opacity 0.2s ease;
}
.modal-fade-enter, .modal-fade-leave-to {
  opacity: 0;
}

/* ==========================================================================
   Секция предложений Alshned Auto Parts
   ========================================================================== */
.alshned-section {
  margin-top: 1.75rem;
  padding-top: 1.5rem;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.alshned-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.25rem;
}

.alshned-title-wrap {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.alshned-badge-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: rgba(99, 102, 241, 0.15);
  border: 1px solid rgba(99, 102, 241, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.35rem;
  flex-shrink: 0;
  box-shadow: 0 4px 14px rgba(99, 102, 241, 0.2);
}

.alshned-title-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.alshned-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--text-main, #ffffff);
  margin: 0;
  letter-spacing: -0.01em;
}

.alshned-counter {
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.2rem 0.65rem;
  border-radius: 9999px;
  letter-spacing: 0.02em;
}

.alshned-counter.badge-found {
  background: rgba(16, 185, 129, 0.18);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.35);
  box-shadow: 0 0 10px rgba(16, 185, 129, 0.15);
}

.alshned-counter.badge-empty {
  background: rgba(148, 163, 184, 0.12);
  color: #94a3b8;
  border: 1px solid rgba(148, 163, 184, 0.25);
}

.alshned-subtitle {
  font-size: 0.82rem;
  color: var(--text-muted, #94a3b8);
  margin: 0.25rem 0 0 0;
}

.link-alshned-site {
  color: #818cf8;
  text-decoration: none;
  font-weight: 600;
  transition: all 0.2s;
}

.link-alshned-site:hover {
  color: #a5b4fc;
  text-decoration: underline;
}

.alshned-search-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.55rem 1.05rem;
  border-radius: var(--radius-sm, 8px);
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.12);
  color: #e2e8f0;
  font-size: 0.82rem;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.alshned-search-btn:hover {
  background: rgba(99, 102, 241, 0.18);
  border-color: rgba(99, 102, 241, 0.45);
  color: #ffffff;
  transform: translateY(-1px);
  box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2);
}

.alshned-search-btn .btn-arrow {
  font-size: 0.95rem;
  transition: transform 0.2s;
}

.alshned-search-btn:hover .btn-arrow {
  transform: translate(2px, -2px);
}

/* Сетка карточек */
.alshned-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
}

.alshned-card {
  background: rgba(15, 23, 42, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.09);
  border-radius: 12px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
  backdrop-filter: blur(12px);
  position: relative;
  width: 100%;
}

.alshned-card:hover {
  border-color: rgba(99, 102, 241, 0.5);
  transform: translateY(-2px);
  box-shadow: 0 6px 22px rgba(99, 102, 241, 0.22);
}

.alshned-card-media {
  position: relative;
  width: 100%;
  height: 110px;
  background: #090e17;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.alshned-img-link {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
}

.alshned-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  transition: transform 0.35s ease;
}

.alshned-card:hover .alshned-img {
  transform: scale(1.06);
}

.alshned-no-img {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #64748b;
  font-size: 1.6rem;
  gap: 0.2rem;
}

.alshned-no-img small {
  font-size: 0.68rem;
  color: #94a3b8;
}

.alshned-status {
  position: absolute;
  top: 0.45rem;
  right: 0.45rem;
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
  font-size: 0.65rem;
  font-weight: 700;
  backdrop-filter: blur(8px);
  letter-spacing: 0.02em;
  z-index: 2;
}

.alshned-status.in-stock {
  background: rgba(16, 185, 129, 0.9);
  color: #ffffff;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.4);
}

.alshned-status.out-of-stock {
  background: rgba(239, 68, 68, 0.85);
  color: #ffffff;
}

.alshned-card-body {
  padding: 0.65rem 0.8rem;
  display: flex;
  flex-direction: column;
  flex: 1;
  gap: 0.35rem;
}

.alshned-card-meta {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.alshned-vendor-tag {
  font-size: 0.62rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #818cf8;
  background: rgba(99, 102, 241, 0.12);
  padding: 0.1rem 0.35rem;
  border-radius: 3px;
  border: 1px solid rgba(99, 102, 241, 0.25);
}

.alshned-item-title {
  font-size: 0.8rem;
  font-weight: 600;
  line-height: 1.35;
  color: #f8fafc;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  min-height: 2.15rem;
  margin: 0;
}

.alshned-item-title a {
  color: inherit;
  text-decoration: none;
  transition: color 0.2s;
}

.alshned-item-title a:hover {
  color: #60a5fa;
}

.alshned-card-bottom {
  margin-top: auto;
  padding-top: 0.5rem;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.alshned-price-wrapper {
  display: flex;
  flex-direction: column;
}

.alshned-price-label {
  font-size: 0.6rem;
  color: #94a3b8;
  text-transform: uppercase;
  font-weight: 600;
  letter-spacing: 0.04em;
}

.alshned-price-val {
  font-size: 1.1rem;
  font-weight: 800;
  color: #34d399;
  letter-spacing: -0.02em;
  line-height: 1.2;
}

.alshned-buy-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.35rem 0.75rem;
  border-radius: 6px;
  background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
  color: #ffffff;
  font-size: 0.76rem;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 2px 10px rgba(79, 70, 229, 0.35);
}

.alshned-buy-btn:hover {
  background: linear-gradient(135deg, #6366f1 0%, #60a5fa 100%);
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(79, 70, 229, 0.5);
  filter: brightness(1.08);
}

.alshned-buy-btn .btn-arrow {
  transition: transform 0.2s;
}

.alshned-buy-btn:hover .btn-arrow {
  transform: translate(2px, -2px);
}

/* Пустое состояние Alshned */
.alshned-empty-box {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  padding: 1.2rem 1.4rem;
  background: rgba(15, 23, 42, 0.45);
  border: 1px dashed rgba(255, 255, 255, 0.14);
  border-radius: 12px;
}

.alshned-empty-box .empty-icon-wrap {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  background: rgba(148, 163, 184, 0.1);
  border: 1px solid rgba(148, 163, 184, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.35rem;
  flex-shrink: 0;
}

.alshned-empty-box .empty-info {
  flex: 1;
}

.alshned-empty-box .empty-title {
  font-size: 0.92rem;
  font-weight: 700;
  color: #e2e8f0;
  margin: 0 0 0.2rem 0;
}

.alshned-empty-box .empty-desc {
  font-size: 0.8rem;
  color: var(--text-muted, #94a3b8);
  margin: 0 0 0.4rem 0;
}

.alshned-empty-box .empty-desc code {
  background: rgba(255, 255, 255, 0.08);
  padding: 0.12rem 0.4rem;
  border-radius: 4px;
  color: #f1f5f9;
  font-family: inherit;
  font-weight: 600;
}

.alshned-empty-box .empty-link {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: #60a5fa;
  text-decoration: none;
  transition: all 0.2s;
}

.alshned-empty-box .empty-link:hover {
  color: #93c5fd;
  text-decoration: underline;
}

@media (max-width: 900px) {
  .alshned-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 600px) {
  .alshned-header {
    flex-direction: column;
    align-items: flex-start;
  }
  .alshned-search-btn {
    width: 100%;
    justify-content: center;
  }
  .alshned-grid {
    grid-template-columns: 1fr;
  }
  .alshned-empty-box {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
