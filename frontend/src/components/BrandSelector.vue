<template>
  <div class="brand-selector-wrap" ref="dropdownRef">
    <div class="selector-label-row">
      <label class="field-label">
        <span class="label-badge badge-brand">🚗</span>
        <span>Марка автомобиля и сайт проверки</span>
      </label>
      <div class="quick-brand-chips" v-if="!disabled">
        <button
          v-for="b in visibleChips"
          :key="b.id"
          type="button"
          class="chip-btn"
          :class="{ active: currentBrand && currentBrand.id === b.id, 'chip-wmi': b.supported === false }"
          @click="selectBrand(b)"
          :title="b.siteUrl ? 'Проверять на ' + b.siteUrl : 'Марка ' + b.name + ' (WMI)'"
        >
          <span class="chip-icon">{{ b.icon || '🚘' }}</span>
          <span>{{ b.name }}</span>
        </button>
      </div>
    </div>

    <!-- Dropdown Trigger Box -->
    <div 
      class="selector-trigger glass-panel"
      :class="{ 'is-open': isOpen, 'is-disabled': disabled, 'is-wmi-detected': currentBrand && currentBrand.supported === false }"
      @click="toggleDropdown"
      tabindex="0"
      @keydown.esc="closeDropdown"
      @keydown.enter.prevent="toggleDropdown"
    >
      <div class="selected-info">
        <span class="brand-icon">{{ (currentBrand && currentBrand.icon) || '🚘' }}</span>
        <div class="brand-text-block">
          <strong class="brand-name">
            {{ (currentBrand && currentBrand.name) || 'Выберите марку' }}
            <span v-if="currentBrand && currentBrand.supported === false" class="badge-auto-tag">WMI Автоопределение</span>
          </strong>
          <span class="site-tag" v-if="currentBrand">
            <template v-if="currentBrand.siteUrl">
              Сайт проверки: <span class="site-host">{{ getHost(currentBrand.siteUrl) }}</span>
            </template>
            <template v-else>
              <span class="status-wmi-note">Марка определена по VIN • Каталог парсинга в разработке</span>
            </template>
          </span>
        </div>
      </div>

      <div class="trigger-actions">
        <span class="site-badge" v-if="currentBrand && currentBrand.siteCode">{{ currentBrand.siteCode }}</span>
        <span class="site-badge badge-wmi" v-else-if="currentBrand">WMI</span>
        <span class="chevron-icon">{{ isOpen ? '▲' : '▼' }}</span>
      </div>
    </div>

    <!-- Dropdown Popup with Search Input -->
    <transition name="dropdown-fade">
      <div v-if="isOpen" class="dropdown-menu glass-panel">
        <div class="search-box-row">
          <span class="search-icon">🔍</span>
          <input
            ref="searchInput"
            type="text"
            v-model="searchQuery"
            placeholder="Поиск марки (BMW, Toyota, Mercedes, Audi...)"
            class="dropdown-search-input"
            @click.stop
            @keydown.esc="closeDropdown"
            autocomplete="off"
            spellcheck="false"
          />
          <button 
            v-if="searchQuery" 
            type="button" 
            class="clear-search-btn" 
            @click.stop="searchQuery = ''"
          >
            ✕
          </button>
        </div>

        <ul class="brands-list">
          <li
            v-for="b in filteredBrands"
            :key="b.id"
            class="brand-option"
            :class="{ selected: currentBrand && currentBrand.id === b.id }"
            @click.stop="selectBrand(b)"
          >
            <div class="option-main">
              <span class="option-icon">{{ b.icon || '🚘' }}</span>
              <div class="option-info">
                <span class="option-name">
                  {{ b.name }}
                  <span v-if="b.supported" class="catalog-ready-tag">Парсинг активен</span>
                </span>
                <span class="option-url" v-if="b.siteUrl">{{ b.siteUrl }}</span>
                <span class="option-wmi" v-else-if="b.wmi">WMI: {{ b.wmi.slice(0, 5).join(', ') }}{{ b.wmi.length > 5 ? '...' : '' }}</span>
              </div>
            </div>

            <div class="option-meta">
              <span class="code-pill" v-if="b.siteCode">{{ b.siteCode }}</span>
              <span class="code-pill pill-wmi" v-else>WMI</span>
              <span v-if="currentBrand && currentBrand.id === b.id" class="selected-check">✓</span>
            </div>
          </li>

          <li v-if="filteredBrands.length === 0" class="no-results">
            Марка "{{ searchQuery }}" не найдена в справочнике
          </li>
        </ul>

        <div class="dropdown-footer">
          <span>Справочник WMI из <code>config/wmi.json</code></span>
        </div>
      </div>
    </transition>
  </div>
</template>

<script>
export default {
  name: 'BrandSelector',
  props: {
    brands: {
      type: Array,
      default: () => []
    },
    value: {
      type: [Object, String],
      default: null
    },
    disabled: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      isOpen: false,
      searchQuery: ''
    };
  },
  computed: {
    currentBrand() {
      if (!this.value) return this.brands[0] || null;
      if (typeof this.value === 'string') {
        return this.brands.find(b => b.id === this.value || b.name === this.value) || this.brands[0];
      }
      return this.value;
    },
    visibleChips() {
      // Поддерживаемые марки (Infiniti, Mercedes) всегда первыми
      const supported = this.brands.filter(b => b.supported !== false);
      // Если текущая выбранная марка не входит в поддерживаемые (например, BMW определена по VIN), показываем её тоже
      if (this.currentBrand && !supported.some(b => b.id === this.currentBrand.id)) {
        return [...supported, this.currentBrand];
      }
      return supported;
    },
    filteredBrands() {
      if (!this.searchQuery) return this.brands;
      const q = this.searchQuery.toLowerCase().trim();
      return this.brands.filter(b => 
        b.name.toLowerCase().includes(q) || 
        b.id.toLowerCase().includes(q) ||
        (b.siteUrl && b.siteUrl.toLowerCase().includes(q)) ||
        (b.wmi && b.wmi.some(w => w.toLowerCase().includes(q)))
      );
    }
  },
  mounted() {
    document.addEventListener('click', this.handleClickOutside);
  },
  beforeDestroy() {
    document.removeEventListener('click', this.handleClickOutside);
  },
  methods: {
    toggleDropdown() {
      if (this.disabled) return;
      this.isOpen = !this.isOpen;
      if (this.isOpen) {
        this.searchQuery = '';
        this.$nextTick(() => {
          if (this.$refs.searchInput) {
            this.$refs.searchInput.focus();
          }
        });
      }
    },
    closeDropdown() {
      this.isOpen = false;
      this.searchQuery = '';
    },
    selectBrand(brand) {
      this.$emit('input', brand);
      this.$emit('change', brand);
      this.closeDropdown();
    },
    handleClickOutside(event) {
      if (this.$refs.dropdownRef && !this.$refs.dropdownRef.contains(event.target)) {
        this.closeDropdown();
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
.brand-selector-wrap {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}

.selector-label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.field-label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-main);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.label-badge.badge-brand {
  background: rgba(99, 102, 241, 0.2);
  color: #a5b4fc;
}

.quick-brand-chips {
  display: flex;
  gap: 0.35rem;
}

.chip-btn {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid var(--border-subtle);
  color: var(--text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.2rem 0.55rem;
  border-radius: 6px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  transition: all 0.2s;
}

.chip-btn:hover {
  background: rgba(255, 255, 255, 0.1);
  color: var(--text-main);
}

.chip-btn.active {
  background: rgba(99, 102, 241, 0.2);
  border-color: rgba(99, 102, 241, 0.4);
  color: #c7d2fe;
}

.chip-icon {
  font-size: 0.85rem;
}

/* Trigger Box */
.selector-trigger {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  background: rgba(18, 24, 40, 0.85);
  border: 1.5px solid var(--border-subtle);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: all 0.25s ease;
  user-select: none;
  outline: none;
}

.selector-trigger:hover:not(.is-disabled) {
  border-color: rgba(99, 102, 241, 0.5);
  background: rgba(24, 32, 54, 0.95);
}

.selector-trigger.is-open {
  border-color: #818cf8;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
}

.selector-trigger.is-disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.selected-info {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.brand-icon {
  font-size: 1.4rem;
  line-height: 1;
}

.brand-text-block {
  display: flex;
  flex-direction: column;
}

.brand-name {
  font-size: 1rem;
  font-weight: 700;
  color: #ffffff;
  letter-spacing: -0.01em;
}

.site-tag {
  font-size: 0.75rem;
  color: var(--text-dim);
}

.site-host {
  color: var(--accent-cyan);
  font-family: var(--font-mono);
  font-weight: 500;
}

.trigger-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.site-badge {
  background: rgba(99, 102, 241, 0.15);
  border: 1px solid rgba(99, 102, 241, 0.35);
  color: #a5b4fc;
  font-family: var(--font-mono);
  font-size: 0.72rem;
  font-weight: 700;
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
}

.chevron-icon {
  font-size: 0.7rem;
  color: var(--text-dim);
  transition: transform 0.2s;
}

/* Dropdown Menu Popup */
.dropdown-menu {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  right: 0;
  z-index: 50;
  background: #0d1322;
  border: 1.5px solid rgba(99, 102, 241, 0.4);
  border-radius: var(--radius-sm);
  box-shadow: 0 12px 35px rgba(0, 0, 0, 0.6);
  padding: 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.search-box-row {
  position: relative;
  display: flex;
  align-items: center;
}

.search-box-row .search-icon {
  position: absolute;
  left: 0.75rem;
  font-size: 0.85rem;
  opacity: 0.6;
}

.dropdown-search-input {
  width: 100%;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  padding: 0.6rem 2.2rem 0.6rem 2.2rem;
  font-size: 0.85rem;
  color: #ffffff;
  outline: none;
  font-family: var(--font-sans);
  transition: border-color 0.2s;
}

.dropdown-search-input:focus {
  border-color: #818cf8;
  background: rgba(255, 255, 255, 0.08);
}

.clear-search-btn {
  position: absolute;
  right: 0.75rem;
  background: none;
  border: none;
  color: var(--text-dim);
  font-size: 0.8rem;
  cursor: pointer;
}

.brands-list {
  list-style: none;
  margin: 0;
  padding: 0;
  max-height: 220px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.brand-option {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.65rem 0.75rem;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s;
}

.brand-option:hover {
  background: rgba(99, 102, 241, 0.15);
}

.brand-option.selected {
  background: rgba(99, 102, 241, 0.25);
  border: 1px solid rgba(99, 102, 241, 0.4);
}

.option-main {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.option-icon {
  font-size: 1.25rem;
}

.option-info {
  display: flex;
  flex-direction: column;
}

.option-name {
  font-size: 0.9rem;
  font-weight: 700;
  color: #ffffff;
}

.option-url {
  font-size: 0.72rem;
  color: var(--text-dim);
  font-family: var(--font-mono);
}

.option-meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.code-pill {
  background: rgba(255, 255, 255, 0.06);
  color: var(--text-muted);
  font-size: 0.68rem;
  font-family: var(--font-mono);
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
}

.selected-check {
  color: #34d399;
  font-weight: 800;
  font-size: 0.9rem;
}

.no-results {
  padding: 1.5rem 1rem;
  text-align: center;
  color: var(--text-dim);
  font-size: 0.85rem;
}

.dropdown-footer {
  border-top: 1px solid var(--border-subtle);
  padding-top: 0.4rem;
  font-size: 0.7rem;
  color: var(--text-dim);
  text-align: right;
}

.dropdown-footer code {
  color: #a5b4fc;
}

/* Animations */
.dropdown-fade-enter-active, .dropdown-fade-leave-active {
  transition: all 0.2s ease;
}
.dropdown-fade-enter, .dropdown-fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

/* WMI Styles */
.badge-auto-tag {
  display: inline-block;
  font-size: 0.68rem;
  background: rgba(99, 102, 241, 0.2);
  border: 1px solid rgba(99, 102, 241, 0.4);
  color: #c7d2fe;
  padding: 0.1rem 0.45rem;
  border-radius: 4px;
  margin-left: 0.5rem;
  font-weight: 600;
  vertical-align: middle;
}

.status-wmi-note {
  font-size: 0.75rem;
  color: #a5b4fc;
}

.badge-wmi {
  background: rgba(99, 102, 241, 0.2);
  border: 1px solid rgba(99, 102, 241, 0.4);
  color: #a5b4fc;
}

.chip-wmi {
  border-color: rgba(99, 102, 241, 0.35);
  background: rgba(99, 102, 241, 0.1);
}

.catalog-ready-tag {
  font-size: 0.65rem;
  background: rgba(16, 185, 129, 0.2);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.3);
  padding: 0.1rem 0.4rem;
  border-radius: 3px;
  margin-left: 0.4rem;
  font-weight: 600;
}

.option-wmi {
  font-size: 0.7rem;
  color: #94a3b8;
  font-family: var(--font-mono);
}

.pill-wmi {
  background: rgba(99, 102, 241, 0.15);
  color: #c7d2fe;
  border: 1px solid rgba(99, 102, 241, 0.25);
}

.selector-trigger.is-wmi-detected {
  border-color: rgba(99, 102, 241, 0.5);
  box-shadow: 0 0 12px rgba(99, 102, 241, 0.15);
}
</style>
