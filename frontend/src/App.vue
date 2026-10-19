<template>
  <div id="app" class="app-container">
    <!-- Top Navigation Bar -->
    <header class="app-header glass-panel">
      <div class="header-brand">
        <div class="brand-logo">
          <span class="logo-text">VIN</span>
        </div>
        <div class="brand-text">
          <h1 class="brand-title">VIN Inspector</h1>
          <span class="brand-subtitle">Панель поиска и парсинга данных авто</span>
        </div>
      </div>

      <div class="header-status">
        <button 
          type="button" 
          class="btn-header-reset" 
          @click="resetState"
          title="Сбросить поиск и очистить данные без перезагрузки страницы"
        >
          <span class="reset-icon">↺</span>
          <span class="reset-text">Сброс</span>
        </button>
      </div>
    </header>

    <!-- Main Content Layout -->
    <main class="main-content">
      <div class="content-left">
        <!-- VIN Search Box (Step 1 & Step 2) -->
        <VinSearch 
          ref="vinSearch"
          :loading="loading" 
          :decoding="decoding"
          :decoded-vehicle="decodedVehicle"
          :vehicle="vehicleData"
          :oem-loading="oemLoading"
          @decode-vin="onDecodeVin"
          @search-catalog="onSearchCatalog"
          @search-oem="onSearchOem"
          @reset-vin="onResetVin"
        />

        <!-- Alerts (Error / Info) -->
        <StatusAlert 
          v-if="error"
          type="error"
          title="Ошибка поиска"
          :message="error"
          @dismiss="error = null"
        />

        <!-- OEM Check Result Display (.pn-check-confirm-text & Vehicle Fitment) -->
        <OemCheckResult 
          :result="oemResult"
          :loading="oemLoading"
          :oem-code="currentOem"
          :vehicle="vehicleData"
          :catalog-host="(vehicleData && vehicleData.siteUrl ? getHost(vehicleData.siteUrl) : (decodedVehicle && decodedVehicle.catalogHost ? decodedVehicle.catalogHost : ''))"
        />

        <StatusAlert 
          v-if="demoNotice"
          type="info"
          title="Режим предпросмотра (Демо)"
          message="Каталог деталей для данной марки подключается, отображаются демонстрационные данные автомобиля."
          @dismiss="demoNotice = false"
        />

        <!-- Loading State Skeleton -->
        <div v-if="loading" class="loading-state glass-panel">
          <div class="spinner-large"></div>
          <h3>Поиск автомобиля в каталоге запчастей...</h3>
          <p>
            Проверяем VIN <span class="highlight-code">{{ currentVin }}</span>
            <span v-if="currentOem"> и деталь <span class="highlight-code">{{ currentOem }}</span></span>, 
            загружаем характеристики и подбираем каталог...
          </p>
        </div>

        <!-- Vehicle Details Card -->
        <VehicleDetails 
          v-else-if="vehicleData" 
          :vehicle="vehicleData" 
        />

        <!-- Welcome Placeholder (if no search yet) -->
        <div v-else class="welcome-box glass-panel">
          <div class="welcome-icon">🚘</div>
          <h2>Готов к работе</h2>
          <p>Введите VIN-код автомобиля для мгновенного определения марки через государственную базу NHTSA и перехода к каталогу деталей.</p>
        </div>
      </div>

      <!-- Right Column: History Sidebar -->
      <aside class="content-right">
        <HistorySidebar 
          :history="history"
          :current-vin="currentVin"
          @select="selectHistoryItem"
          @remove="removeFromHistory"
          @clear="clearHistory"
        />
      </aside>
    </main>

    <!-- Footer -->
    <footer class="app-footer">
      <p>VIN Inspector • Система поиска и проверки автозапчастей по VIN и OEM</p>
    </footer>
  </div>
</template>

<script>
import VinSearch from './components/VinSearch.vue';
import VehicleDetails from './components/VehicleDetails.vue';
import HistorySidebar from './components/HistorySidebar.vue';
import StatusAlert from './components/StatusAlert.vue';
import OemCheckResult from './components/OemCheckResult.vue';

export default {
  name: 'App',
  components: {
    VinSearch,
    VehicleDetails,
    HistorySidebar,
    StatusAlert,
    OemCheckResult
  },
  data() {
    return {
      brands: [],
      currentVin: '',
      currentOem: '',
      decoding: false,
      decodedVehicle: null,
      loading: false,
      oemLoading: false,
      error: null,
      demoNotice: false,
      vehicleData: null,
      oemResult: null,
      history: []
    };
  },
  created() {
    this.loadBrands();
    this.loadHistory();
  },
  methods: {
    async loadBrands() {
      try {
        const res = await fetch('/api/get-brands.php');
        if (res.ok) {
          const data = await res.json();
          if (data.success && data.brands) {
            this.brands = data.brands;
          }
        }
      } catch (e) {
        console.warn('Could not load brands from API', e);
      }
    },

    async onDecodeVin(vin) {
      this.currentVin = vin;
      this.currentOem = '';
      this.error = null;
      this.oemResult = null;
      this.vehicleData = null;
      this.decodedVehicle = null;
      this.decoding = true;
      this.demoNotice = false;

      try {
        const res = await fetch(`/api/decode-vin.php?vin=${encodeURIComponent(vin)}`);
        if (res.ok) {
          const data = await res.json();
          if (data.success) {
            this.decodedVehicle = data;
          } else {
            this.error = data.error || 'Автомобиль не найден в базе NHTSA';
          }
        } else {
          this.error = `Сервер ответил ошибкой HTTP ${res.status}`;
        }
      } catch (err) {
        this.error = 'Не удалось связаться с сервисом NHTSA: ' + err.message;
      } finally {
        this.decoding = false;
      }
    },

    async onSearchCatalog(payload) {
      const vin = (payload && payload.vin) || this.currentVin;
      const brandId = (payload && payload.brandId) || (this.decodedVehicle && this.decodedVehicle.brandId) || '';

      this.loading = true;
      this.error = null;
      this.oemResult = null;

      try {
        const response = await fetch(`/api/check-vin.php?vin=${encodeURIComponent(vin)}&brand=${encodeURIComponent(brandId)}`, {
          headers: { 'Accept': 'application/json' }
        });

        if (response.ok) {
          const result = await response.json();
          if (result.success && result.data) {
            this.vehicleData = result.data;
            this.saveToHistory(this.vehicleData);
            this.demoNotice = false;
            return;
          } else {
            // Если каталог пока не настроен для этой марки (например, BMW или Toyota)
            if (this.decodedVehicle && !this.decodedVehicle.hasCatalog) {
              this.useMockData(vin);
              this.demoNotice = true;
              return;
            }
            this.error = result.error || 'Данные по данному VIN не найдены в каталоге деталей';
          }
        } else {
          this.error = `Сервер бэкенда ответил ошибкой HTTP ${response.status}`;
        }
      } catch (err) {
        this.error = 'Не удалось связаться с сервером бэкенда: ' + err.message;
      } finally {
        this.loading = false;
      }
    },

    async onSearchOem(payload) {
      const vin = payload.vin;
      const oem = payload.oem;
      const brandId = payload.brand || (this.vehicleData && this.vehicleData.brandId) || (this.decodedVehicle && this.decodedVehicle.brandId) || '';

      this.currentOem = oem;
      this.oemLoading = true;
      this.error = null;
      this.oemResult = null;

      try {
        const categoryUrl = (this.vehicleData && this.vehicleData.url) || '';
        const response = await fetch(
          `/api/check-oem.php?vin=${encodeURIComponent(vin)}&oem=${encodeURIComponent(oem)}&brand=${encodeURIComponent(brandId)}&categoryUrl=${encodeURIComponent(categoryUrl)}`,
          { headers: { 'Accept': 'application/json' } }
        );

        if (response.ok) {
          const result = await response.json();
          if (result.success) {
            this.oemResult = result;
            if (this.vehicleData) {
              this.vehicleData = {
                ...this.vehicleData,
                oem,
                partTitle: result.partTitle,
                oemStatus: result.status,
                confirmText: result.confirmText,
                oemResult: result
              };
              this.saveToHistory(this.vehicleData);
            }
          } else {
            this.error = result.error || 'Деталь не найдена в каталоге';
          }
        } else {
          this.error = `Сервер ответил ошибкой HTTP ${response.status} при проверке OEM`;
        }
      } catch (err) {
        this.error = 'Не удалось связаться с сервером при проверке OEM: ' + err.message;
      } finally {
        this.oemLoading = false;
      }
    },

    onResetVin() {
      this.vehicleData = null;
      this.currentVin = '';
      this.currentOem = '';
      this.oemResult = null;
      this.error = null;
    },

    useMockData(vin, oem) {
      this.demoNotice = true;
      
      // Генератор реалистичных данных под различные марки для тестирования
      let mock;
      if (vin.startsWith('5UX')) {
        mock = {
          vin: vin,
          oem: oem || '',
          make: 'BMW',
          model: 'X5',
          trim: 'xDrive40i M Sport',
          year: 2021,
          bodyClass: 'Кроссовер / SUV',
          color: 'Черный металлик (Black Sapphire)',
          country: 'США (Spartanburg Plant)',
          engine: '3.0L B58 Turbocharged Inline-6',
          displacement: '2998 см³',
          horsepower: '340 л.с.',
          fuelType: 'Бензин (АИ-98/95)',
          driveType: 'Полный (AWD / xDrive)',
          transmission: '8-ступенчатый автомат (ZF Steptronic)',
          extra: {
            partOem: oem || 'Не указан',
            doors: '5 дверей',
            seats: '5 мест',
            wheelbase: '2975 мм',
            steering: 'Электроусилитель с переменным усилием',
            brakes: 'Дисковые вентилируемые'
          }
        };
      } else if (vin.startsWith('4T1')) {
        mock = {
          vin: vin,
          oem: oem || '',
          make: 'Toyota',
          model: 'Camry',
          trim: 'SE 2.5',
          year: 2020,
          bodyClass: 'Седан',
          color: 'Белый перламутр (White Pearl)',
          country: 'США / Япония',
          engine: '2.5L Dynamic Force A25A-FKS',
          displacement: '2487 см³',
          horsepower: '206 л.с.',
          fuelType: 'Бензин',
          driveType: 'Передний (FWD)',
          transmission: '8-ступенчатый Direct Shift AT',
          extra: {
            partOem: oem || 'Не указан',
            doors: '4 двери',
            seats: '5 мест',
            fuelCapacity: '60 л',
            brakes: 'Дисковые'
          }
        };
      } else if (vin.startsWith('WDD')) {
        mock = {
          vin: vin,
          oem: oem || '',
          make: 'Mercedes-Benz',
          model: 'E-Class',
          trim: 'E200 4MATIC',
          year: 2019,
          bodyClass: 'Седан',
          color: 'Серый селенит (Selenite Grey)',
          country: 'Германия (Sindelfingen)',
          engine: '2.0L M274 Turbo I4',
          displacement: '1991 см³',
          horsepower: '197 л.с.',
          fuelType: 'Бензин',
          driveType: 'Полный (4MATIC)',
          transmission: '9-ступенчатый 9G-TRONIC',
          extra: {
            partOem: oem || 'Не указан',
            doors: '4 двери',
            seats: '5 мест',
            platform: 'MRA'
          }
        };
      } else {
        mock = {
          vin: vin,
          oem: oem || '',
          make: 'Ford',
          model: 'Mustang',
          trim: 'GT Fastback',
          year: 2022,
          bodyClass: 'Купе',
          color: 'Красный (Race Red)',
          country: 'США (Flat Rock)',
          engine: '5.0L Ti-VCT V8 Coyote',
          displacement: '4951 см³',
          horsepower: '450 л.с.',
          fuelType: 'Бензин',
          driveType: 'Задний (RWD)',
          transmission: '10-ступенчатый автомат',
          extra: {
            partOem: oem || 'Не указан',
            doors: '2 двери',
            seats: '4 места'
          }
        };
      }

      this.vehicleData = mock;
      this.saveToHistory(mock);
    },

    saveToHistory(vehicle) {
      if (!vehicle || !vehicle.vin) return;
      
      const title = `${vehicle.make || ''} ${vehicle.model || ''} (${vehicle.year || ''})`.trim();
      
      // Исключаем дубликат того же VIN
      this.history = this.history.filter((item) => item.vin !== vehicle.vin);
      
      // Добавляем в начало
      this.history.unshift({
        vin: vehicle.vin,
        oem: vehicle.oem || '',
        title: title || 'Автомобиль',
        timestamp: Date.now(),
        data: vehicle
      });

      // Храним максимум 20 записей
      if (this.history.length > 20) {
        this.history.pop();
      }

      this.persistHistory();
    },

    selectHistoryItem(item) {
      this.currentVin = item.vin;
      this.currentOem = item.oem || '';
      this.vehicleData = item.data;
      this.decodedVehicle = {
        vin: item.vin,
        carTitle: (item.data && item.data.title) || item.title,
        catalogHost: (item.data && item.data.siteUrl) ? this.getHost(item.data.siteUrl) : ''
      };
      this.oemResult = (item.data && item.data.oemResult) ? item.data.oemResult : null;
      this.error = null;
      this.demoNotice = false;
    },

    getHost(url) {
      if (!url) return '';
      try {
        const u = new URL(url);
        return u.hostname;
      } catch (e) {
        return url.replace(/^https?:\/\//, '').replace(/\/.*$/, '');
      }
    },

    removeFromHistory(vin) {
      this.history = this.history.filter((item) => item.vin !== vin);
      this.persistHistory();
    },

    clearHistory() {
      this.history = [];
      this.persistHistory();
    },

    loadHistory() {
      try {
        const raw = localStorage.getItem('vin_inspector_history');
        if (raw) {
          this.history = JSON.parse(raw);
        }
      } catch (e) {
        this.history = [];
      }
    },

    persistHistory() {
      try {
        localStorage.setItem('vin_inspector_history', JSON.stringify(this.history));
      } catch (e) {
        // игнорируем переполнение quota
      }
    },

    resetState() {
      this.currentVin = '';
      this.currentOem = '';
      this.decoding = false;
      this.decodedVehicle = null;
      this.loading = false;
      this.oemLoading = false;
      this.error = null;
      this.demoNotice = false;
      this.vehicleData = null;
      this.oemResult = null;
      if (this.$refs.vinSearch && typeof this.$refs.vinSearch.resetAll === 'function') {
        this.$refs.vinSearch.resetAll();
      }
    },

    onResetVin() {
      this.currentVin = '';
      this.currentOem = '';
      this.decodedVehicle = null;
      this.vehicleData = null;
      this.oemResult = null;
      this.error = null;
      this.demoNotice = false;
    }
  }
};
</script>

<style scoped>
.app-container {
  max-width: 1320px;
  margin: 0 auto;
  padding: 1.5rem 1.5rem 3rem;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

/* Header */
.app-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.75rem;
  margin-bottom: 2rem;
  border-radius: var(--radius-md);
}

.header-brand {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.brand-logo {
  background: var(--accent-gradient);
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 15px rgba(56, 189, 248, 0.35);
}

.logo-text {
  font-family: var(--font-mono);
  font-weight: 800;
  color: #ffffff;
  font-size: 1rem;
  letter-spacing: 0.05em;
}

.brand-title {
  font-size: 1.25rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--text-main);
  line-height: 1.2;
}

.brand-subtitle {
  font-size: 0.8rem;
  color: var(--text-dim);
}

.header-status {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.btn-header-reset {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.15);
  color: var(--text-muted, #94a3b8);
  padding: 0.4rem 0.85rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  user-select: none;
}

.btn-header-reset:hover {
  background: rgba(56, 189, 248, 0.12);
  border-color: rgba(56, 189, 248, 0.4);
  color: #38bdf8;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(56, 189, 248, 0.2);
}

.btn-header-reset:active {
  transform: translateY(0);
}

.btn-header-reset .reset-icon {
  font-size: 1rem;
  line-height: 1;
  display: inline-block;
  transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.btn-header-reset:hover .reset-icon {
  transform: rotate(-180deg);
}

/* Layout */
.main-content {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 1.75rem;
  flex: 1;
}

.content-left {
  display: flex;
  flex-direction: column;
}

/* Loading State */
.loading-state {
  padding: 4rem 2rem;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

.spinner-large {
  width: 3.5rem;
  height: 3.5rem;
  border: 3px solid rgba(56, 189, 248, 0.2);
  border-top-color: var(--accent-cyan);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.loading-state h3 {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--text-main);
}

.loading-state p {
  color: var(--text-muted);
  font-size: 0.9rem;
  max-width: 480px;
}

/* Welcome Box */
.welcome-box {
  padding: 3.5rem 2rem;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.welcome-icon {
  font-size: 3rem;
  margin-bottom: 1rem;
  opacity: 0.9;
}

.welcome-box h2 {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--text-main);
  margin-bottom: 0.5rem;
}

.welcome-box p {
  color: var(--text-muted);
  font-size: 0.95rem;
  max-width: 500px;
  line-height: 1.5;
}

/* Footer */
.app-footer {
  margin-top: 3rem;
  text-align: center;
  padding-top: 1.5rem;
  border-top: 1px solid var(--border-subtle);
  color: var(--text-dim);
  font-size: 0.82rem;
}

@media (max-width: 980px) {
  .main-content {
    grid-template-columns: 1fr;
  }
}
</style>
