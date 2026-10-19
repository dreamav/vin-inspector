<template>
  <div class="search-box glass-panel">
    <div class="search-header">
      <div class="title-wrap">
        <span class="search-icon">🔍</span>
        <div>
          <h2>Проверка автомобиля и деталей</h2>
          <p class="subtitle">
            <span v-if="!vehicle">Шаг 1: Выберите марку и введите VIN для определения авто</span>
            <span v-else class="step2-subtitle">Шаг 2: Введите OEM-код запчасти для проверки совместимости</span>
          </p>
        </div>
      </div>
      <div v-if="!vehicle" class="presets">
        <span class="preset-label">Примеры:</span>
        <button 
          v-for="item in activePresets" 
          :key="item.vin"
          type="button" 
          class="btn-preset" 
          @click="selectPreset(item)"
          :title="item.label"
        >
          <span class="preset-icon" v-if="item.icon">{{ item.icon }}</span>
          {{ item.short }}
        </button>
      </div>
    </div>

    <form @submit.prevent="handleSubmit" class="search-form">
      <div class="fields-stack">
        <!-- Блок 0: Выпадающий список с поиском марок машин -->
        <div class="field-group brand-select-group">
          <BrandSelector
            :brands="brandsList"
            :value="chosenBrand"
            :disabled="loading || !!vehicle"
            @change="handleBrandChange"
          />
        </div>

        <!-- Поле 1: VIN код (Шаг 1) -->
        <div class="field-group">
          <div class="field-label-row">
            <label for="vin-input" class="field-label">
              <span class="label-badge" :class="{ 'badge-done': vehicle }">
                {{ vehicle ? '✓' : '1' }}
              </span>
              <span>VIN-код автомобиля</span>
              <span class="required-tag" v-if="!vehicle">Обязательно</span>
            </label>
            <button 
              v-if="vehicle" 
              type="button" 
              class="btn-change-vin" 
              @click="changeVin"
              title="Ввести другой VIN"
            >
              Сменить VIN / марку
            </button>
          </div>

          <div class="input-wrapper" :class="{ 'has-error': vinError, 'is-locked': !!vehicle }">
            <input
              id="vin-input"
              ref="vinInput"
              type="text"
              v-model="vinInput"
              @input="onVinInput"
              :placeholder="vinPlaceholder"
              maxlength="17"
              autocomplete="off"
              spellcheck="false"
              :disabled="loading || !!vehicle"
            />
            <div class="input-meta">
              <span class="char-count" :class="{ valid: vinInput.length === 17 }">
                {{ vinInput.length }}/17
              </span>
              <button 
                v-if="vinInput.length > 0 && !vehicle" 
                type="button" 
                class="clear-btn" 
                @click="clearVinInput"
                title="Очистить VIN"
              >
                ✕
              </button>
            </div>
          </div>
          <div v-if="vinError" class="validation-warning">
            ⚠️ {{ vinError }}
          </div>

          <!-- Плашка успешного определения авто -->
          <div v-if="vehicle" class="vehicle-identified-banner">
            <span class="check-icon">✓</span>
            <div class="identified-info">
              <span class="identified-label">Автомобиль успешно определен:</span>
              <strong class="identified-title">{{ vehicle.title || (vehicle.make + ' ' + (vehicle.model || '')) }}</strong>
              <span class="site-tag-inline" v-if="vehicle.siteUrl">
                (каталог: {{ getHost(vehicle.siteUrl) }})
              </span>
            </div>
          </div>
        </div>

        <!-- Поле 2: OEM-код запчасти (Шаг 2 - ПОЯВЛЯЕТСЯ ТОЛЬКО ПОСЛЕ ОПРЕДЕЛЕНИЯ МАШИНЫ) -->
        <transition name="slide-fade">
          <div v-if="vehicle" class="field-group oem-group">
            <label for="oem-input" class="field-label">
              <span class="label-badge badge-active">2</span>
              <span>OEM-код запчасти</span>
              <span class="badge-step2-tag">
                Проверка на {{ (chosenBrand && chosenBrand.name) || 'каталоге' }} ({{ siteHost }})
              </span>
            </label>
            <div class="input-wrapper oem-wrapper" :class="{ 'has-error': oemError }">
              <input
                id="oem-input"
                ref="oemInput"
                type="text"
                v-model="oemInput"
                @input="onOemInput"
                :placeholder="oemPlaceholder"
                autocomplete="off"
                spellcheck="false"
                :disabled="oemLoading"
              />
              <div class="input-meta">
                <button 
                  v-if="oemInput.length > 0" 
                  type="button" 
                  class="clear-btn" 
                  @click="clearOemInput"
                  title="Очистить OEM"
                >
                  ✕
                </button>
              </div>
            </div>
            <div v-if="oemError" class="validation-warning">
              ⚠️ {{ oemError }}
            </div>
          </div>
        </transition>
      </div>

      <div class="form-actions">
        <!-- Кнопка Шага 1: Определить авто -->
        <button 
          v-if="!vehicle"
          type="submit" 
          class="btn-primary submit-btn" 
          :disabled="!isValidVin || loading"
        >
          <span v-if="loading" class="spinner"></span>
          <span v-else>Найти на {{ siteHost }}</span>
          <span v-if="!loading" class="arrow-icon">→</span>
        </button>

        <!-- Кнопка Шага 2: Проверить OEM деталь -->
        <button 
          v-else
          type="submit" 
          class="btn-primary submit-btn btn-oem-submit" 
          :disabled="!isValidOem || oemLoading"
        >
          <span v-if="oemLoading" class="spinner"></span>
          <span v-else>Проверить OEM деталь</span>
          <span v-if="!oemLoading" class="arrow-icon">🔍</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script>
import BrandSelector from './BrandSelector.vue';

const DEFAULT_BRANDS = [
  {
    id: 'infiniti',
    name: 'Infiniti',
    siteUrl: 'https://www.infinitipartsdeal.com',
    siteCode: 'IPD',
    icon: '🏎️',
    vinPlaceholder: 'Например: 3PCAJ5M34KF147171',
    oemPlaceholder: 'Например: 48430-5NB0A или 22401-1LA1C'
  },
  {
    id: 'mercedes-benz',
    name: 'Mercedes-Benz',
    siteUrl: 'https://www.mbpartsgiant.com',
    siteCode: 'MBPG',
    icon: '⭐',
    vinPlaceholder: 'Например: 4JGDA5HB8GA646272',
    oemPlaceholder: 'Например: 2761800009 или 000989680511'
  }
];

export default {
  name: 'VinSearch',
  components: {
    BrandSelector
  },
  props: {
    loading: {
      type: Boolean,
      default: false
    },
    vehicle: {
      type: Object,
      default: null
    },
    oemLoading: {
      type: Boolean,
      default: false
    },
    brands: {
      type: Array,
      default: () => []
    },
    selectedBrand: {
      type: [Object, String],
      default: null
    }
  },
  data() {
    return {
      vinInput: '',
      oemInput: '',
      vinError: null,
      oemError: null,
      chosenBrand: null,
      allPresets: [
        { 
          short: 'Infiniti QX50', 
          label: '2019 Infiniti QX50 (3PCAJ5M34KF147171)', 
          vin: '3PCAJ5M34KF147171', 
          oem: '48430-5NB0A',
          brandId: 'infiniti',
          icon: '🏎️'
        },
        { 
          short: 'Mercedes GLE350', 
          label: '2016 Mercedes-Benz GLE350 (4JGDA5HB8GA646272)', 
          vin: '4JGDA5HB8GA646272', 
          oem: '2761800009',
          brandId: 'mercedes-benz',
          icon: '⭐'
        }
      ]
    };
  },
  watch: {
    selectedBrand: {
      immediate: true,
      handler(newVal) {
        if (newVal) {
          if (typeof newVal === 'string') {
            this.chosenBrand = this.brandsList.find(b => b.id === newVal) || this.brandsList[0];
          } else {
            this.chosenBrand = newVal;
          }
        } else if (!this.chosenBrand && this.brandsList.length) {
          this.chosenBrand = this.brandsList[0];
        }
      }
    },
    vehicle(newVal) {
      if (newVal) {
        if (newVal.vin) {
          this.vinInput = newVal.vin;
        }
        if (newVal.oem) {
          this.oemInput = newVal.oem;
        }
        if (newVal.brandId && this.brandsList) {
          const b = this.brandsList.find(x => x.id === newVal.brandId);
          if (b) this.chosenBrand = b;
        }
        this.$nextTick(() => {
          if (this.$refs.oemInput) {
            this.$refs.oemInput.focus();
          }
        });
      }
    }
  },
  computed: {
    brandsList() {
      return (this.brands && this.brands.length) ? this.brands : DEFAULT_BRANDS;
    },
    siteHost() {
      if (!this.chosenBrand || !this.chosenBrand.siteUrl) return 'каталоге';
      return this.getHost(this.chosenBrand.siteUrl);
    },
    vinPlaceholder() {
      if (this.chosenBrand && this.chosenBrand.vinPlaceholder) {
        return this.chosenBrand.vinPlaceholder;
      }
      return 'Например: 3PCAJ5M34KF147171 или 4JGDA5HB8GA646272';
    },
    oemPlaceholder() {
      if (this.chosenBrand && this.chosenBrand.oemPlaceholder) {
        return this.chosenBrand.oemPlaceholder;
      }
      return 'Например: 48430-5NB0A или 2761800009';
    },
    activePresets() {
      return this.allPresets;
    },
    isValidVin() {
      const clean = this.vinInput.trim().toUpperCase();
      return clean.length === 17 && !/[IOQ]/i.test(clean) && /^[A-HJ-NPR-Z0-9]{17}$/i.test(clean);
    },
    isValidOem() {
      return this.oemInput.trim().length >= 3;
    }
  },
  methods: {
    handleBrandChange(brand) {
      this.chosenBrand = brand;
      this.$emit('brand-change', brand);
    },
    onVinInput(event) {
      let val = event.target.value.toUpperCase().replace(/\s+/g, '');
      
      if (/[IOQ]/.test(val)) {
        this.vinError = 'VIN-код не может содержать буквы I, O, Q';
      } else {
        this.vinError = null;
      }

      this.vinInput = val;
    },
    onOemInput(event) {
      this.oemInput = event.target.value.toUpperCase().trimStart();
      this.oemError = null;
    },
    selectPreset(item) {
      if (item.brandId && this.brandsList) {
        const found = this.brandsList.find(b => b.id === item.brandId);
        if (found) {
          this.chosenBrand = found;
          this.$emit('brand-change', found);
        }
      }
      this.vinInput = item.vin;
      this.oemInput = item.oem || '';
      this.vinError = null;
      this.$emit('search-vin', {
        vin: this.vinInput,
        brand: (this.chosenBrand && this.chosenBrand.id) || item.brandId || 'infiniti'
      });
    },
    clearVinInput() {
      this.vinInput = '';
      this.vinError = null;
      this.$nextTick(() => {
        this.$refs.vinInput.focus();
      });
    },
    clearOemInput() {
      this.oemInput = '';
      this.$nextTick(() => {
        this.$refs.oemInput.focus();
      });
    },
    changeVin() {
      this.$emit('reset-vin');
      this.$nextTick(() => {
        this.$refs.vinInput.focus();
      });
    },
    handleSubmit() {
      const brandId = (this.chosenBrand && this.chosenBrand.id) || 'infiniti';
      if (!this.vehicle) {
        // Шаг 1: Поиск авто по VIN
        if (!this.isValidVin) {
          if (this.vinInput.length < 17) {
            this.vinError = `Длина VIN должна быть ровно 17 символов (введено ${this.vinInput.length})`;
          }
          return;
        }
        this.vinError = null;
        this.$emit('search-vin', {
          vin: this.vinInput.trim().toUpperCase(),
          brand: brandId
        });
      } else {
        // Шаг 2: Проверка OEM детали
        if (!this.isValidOem) {
          this.oemError = 'Введите корректный OEM-код детали (минимум 3 символа)';
          return;
        }
        this.oemError = null;
        this.$emit('search-oem', {
          vin: this.vinInput.trim().toUpperCase(),
          oem: this.oemInput.trim().toUpperCase(),
          brand: brandId
        });
      }
    },
    getHost(url) {
      if (!url) return '';
      try {
        const u = new URL(url);
        return u.hostname;
      } catch (e) {
        return url.replace(/^https?:\/\//, '').replace(/\/.*$/, '');
      }
    }
  }
};
</script>

<style scoped>
.search-box {
  padding: 1.75rem 2rem;
  margin-bottom: 2rem;
  position: relative;
  overflow: visible;
}

.search-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.title-wrap {
  display: flex;
  align-items: center;
  gap: 0.9rem;
}

.search-icon {
  font-size: 1.8rem;
  background: rgba(56, 189, 248, 0.1);
  padding: 0.5rem;
  border-radius: 12px;
  border: 1px solid rgba(56, 189, 248, 0.2);
}

.title-wrap h2 {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--text-main);
  letter-spacing: -0.02em;
}

.subtitle {
  font-size: 0.85rem;
  color: var(--text-muted);
  margin-top: 0.2rem;
}

.step2-subtitle {
  color: #a5b4fc;
  font-weight: 500;
}

.presets {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.preset-label {
  font-size: 0.78rem;
  color: var(--text-dim);
  margin-right: 0.2rem;
}

.btn-preset {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid var(--border-subtle);
  color: var(--accent-cyan);
  padding: 0.35rem 0.65rem;
  border-radius: 6px;
  font-size: 0.78rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
}

.preset-icon {
  font-size: 0.85rem;
}

.btn-preset:hover {
  background: rgba(56, 189, 248, 0.15);
  border-color: rgba(56, 189, 248, 0.4);
  transform: translateY(-1px);
}

.search-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.fields-stack {
  display: flex;
  flex-direction: column;
  gap: 1.2rem;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}

.brand-select-group {
  margin-bottom: 0.25rem;
}

.field-label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.field-label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-main);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.label-badge {
  background: rgba(56, 189, 248, 0.2);
  color: var(--accent-cyan);
  width: 22px;
  height: 22px;
  border-radius: 50%;
  font-size: 0.75rem;
  font-family: var(--font-mono);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
}

.label-badge.badge-done {
  background: rgba(16, 185, 129, 0.2);
  color: var(--success);
}

.label-badge.badge-active {
  background: rgba(99, 102, 241, 0.25);
  color: #a5b4fc;
}

.required-tag {
  font-size: 0.72rem;
  color: var(--accent-cyan);
  background: rgba(56, 189, 248, 0.1);
  padding: 0.1rem 0.45rem;
  border-radius: 4px;
  font-weight: 500;
}

.badge-step2-tag {
  font-size: 0.72rem;
  color: #a5b4fc;
  background: rgba(99, 102, 241, 0.15);
  border: 1px solid rgba(99, 102, 241, 0.3);
  padding: 0.1rem 0.45rem;
  border-radius: 4px;
  font-weight: 500;
}

.btn-change-vin {
  background: none;
  border: 1px solid var(--border-subtle);
  color: var(--text-dim);
  font-size: 0.75rem;
  padding: 0.2rem 0.6rem;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-change-vin:hover {
  color: var(--accent-cyan);
  border-color: var(--accent-cyan);
}

.input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.input-wrapper input {
  width: 100%;
  background: var(--bg-input);
  border: 1.5px solid var(--border-subtle);
  color: #ffffff;
  font-family: var(--font-mono);
  font-size: 1.15rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  padding: 0.85rem 4.5rem 0.85rem 1.1rem;
  border-radius: var(--radius-sm);
  outline: none;
  transition: all 0.25s ease;
  box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.4);
}

.input-wrapper.is-locked input {
  opacity: 0.75;
  background: rgba(13, 19, 33, 0.5);
  border-color: rgba(16, 185, 129, 0.3);
}

.oem-wrapper input {
  font-size: 1.15rem;
  letter-spacing: 0.05em;
  border-color: rgba(99, 102, 241, 0.35);
  background: rgba(18, 22, 40, 0.95);
}

.oem-wrapper input:focus {
  border-color: #818cf8;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25), inset 0 2px 4px rgba(0, 0, 0, 0.4);
}

.input-wrapper input:focus {
  border-color: var(--border-focus);
  box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.25), inset 0 2px 4px rgba(0, 0, 0, 0.4);
}

.input-wrapper.has-error input {
  border-color: var(--danger);
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2);
}

.input-meta {
  position: absolute;
  right: 1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.char-count {
  font-family: var(--font-mono);
  font-size: 0.8rem;
  color: var(--text-dim);
  background: rgba(0, 0, 0, 0.3);
  padding: 0.2rem 0.45rem;
  border-radius: 4px;
}

.char-count.valid {
  color: var(--success);
  background: var(--success-bg);
  font-weight: 700;
}

.clear-btn {
  background: none;
  border: none;
  color: var(--text-dim);
  cursor: pointer;
  font-size: 0.9rem;
  padding: 0.2rem;
  transition: color 0.2s;
}

.clear-btn:hover {
  color: var(--text-main);
}

.vehicle-identified-banner {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.25);
  padding: 0.65rem 0.9rem;
  border-radius: var(--radius-sm);
  margin-top: 0.35rem;
  animation: fadeIn 0.3s ease;
}

.check-icon {
  background: var(--success);
  color: #ffffff;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 800;
}

.identified-info {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.identified-label {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.identified-title {
  color: #a7f3d0;
  font-size: 0.95rem;
  font-weight: 700;
}

.site-tag-inline {
  font-size: 0.78rem;
  color: var(--text-dim);
  font-family: var(--font-mono);
}

.form-actions {
  display: flex;
  justify-content: flex-start;
  margin-top: 0.25rem;
}

.submit-btn {
  min-width: 200px;
}

.btn-oem-submit {
  background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
  box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.btn-oem-submit:hover:not(:disabled) {
  box-shadow: 0 6px 22px rgba(99, 102, 241, 0.45);
}

.arrow-icon {
  font-size: 1.1rem;
  transition: transform 0.2s;
}

.submit-btn:hover .arrow-icon {
  transform: translateX(3px);
}

.validation-warning {
  margin-top: 0.35rem;
  color: #fca5a5;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

/* Transitions */
.slide-fade-enter-active {
  transition: all 0.35s ease;
}
.slide-fade-leave-active {
  transition: all 0.25s cubic-bezier(1, 0.5, 0.8, 1);
}
.slide-fade-enter, .slide-fade-leave-to {
  transform: translateY(-10px);
  opacity: 0;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 768px) {
  .submit-btn {
    width: 100%;
  }
}
</style>
