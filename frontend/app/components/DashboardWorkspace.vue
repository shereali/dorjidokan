<script setup lang="ts">
interface O {
  id?: string;
  number: string;
  order_number?: string;
  status: string;
  customer: { name: string; mobile_number?: string };
  garment: { name: string };
  total_minor?: number;
  paid_minor?: number;
}

interface DueOrder {
  id: string;
  order_number: string;
  customer: { name: string; mobile_number: string };
  garment: { name: string };
  karigar?: { name: string } | null;
  status: string;
  promised_at?: string;
  total_minor?: number;
  paid_minor?: number;
  due_minor?: number;
}

interface PipelineStages {
  measuring: number;
  pending_assignment: number;
  in_progress: number;
  ready: number;
  delivered: number;
}

interface D {
  metrics: {
    due_today: number;
    in_progress: number;
    ready: number;
    revenue_minor: number;
    pipeline?: PipelineStages;
    overdue_count?: number;
    total_receivables_minor?: number;
    craftsmen_count?: number;
    low_stock_count?: number;
  };
  due_today_orders: DueOrder[];
  recent_orders: O[];
  onboarding: Array<{ key: string; label: string; complete: boolean }>;
}

const api = useTailorsApi();
const { t, locale } = useTailorsI18n();
const data = ref<D | null>(null);
const loading = ref(true);
const error = ref("");

const emit = defineEmits<{
  navigate: [string];
}>();

const todayLabel = new Intl.DateTimeFormat(undefined, {
  weekday: "long",
  day: "numeric",
  month: "long",
  year: "numeric",
}).format(new Date());

const dueTime = (iso?: string) =>
  iso ? new Date(iso).toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" }) : "";

function getWhatsAppUrl(mobile: string, name: string, orderNum: string) {
  const digits = mobile.replace(/\D/g, "");
  const num = digits.startsWith("88") ? digits : digits.startsWith("0") ? "88" + digits : digits;
  const msg = `Hello ${name}, your bespoke tailoring order #${orderNum} is ready/in progress at Suto Tailors Atelier. Thank you!`;
  return `https://wa.me/${num}?text=${encodeURIComponent(msg)}`;
}

async function load() {
  loading.value = true;
  try {
    data.value = (await api.request<D>("/dashboard")).data;
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || "Could not load dashboard.";
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div class="dashboard-root">
    <!-- EXECUTIVE ATELIER WELCOME HERO BANNER -->
    <header class="atelier-hero-banner">
      <div class="hero-left">
        <div class="hero-status-tag">
          <span class="live-dot-pulse"></span>
          <span class="hero-tag-text">ATELIER MASTER DESK · {{ todayLabel }}</span>
        </div>
        <h1 class="hero-title">
          {{ locale === 'bn' ? 'শুভ দিন, সুতো টেইলার্স ওয়ার্কশপ' : 'Bespoke Atelier Operations' }}
        </h1>
        <p class="hero-subtitle">
          Real-time production pipeline, craftsmen scheduling, fabric inventory & delivery promises.
        </p>
      </div>

      <div class="hero-actions">
        <button class="hero-btn-primary" @click="emit('navigate', 'Orders')">
          <NavIcon name="Plus" />
          <span>{{ locale === 'bn' ? '+ নতুন অর্ডার বুকিং' : '+ Book Bespoke Order' }}</span>
        </button>
        <button class="hero-btn-secondary" @click="emit('navigate', 'Operations')">
          <NavIcon name="Cash" />
          <span>{{ locale === 'bn' ? 'কাপড় বিক্রয় POS' : 'Fabric POS Desk' }}</span>
        </button>
      </div>
    </header>

    <p v-if="error" class="error-banner">{{ error }}</p>

    <!-- LIVE 5-STAGE PRODUCTION PIPELINE FUNNEL -->
    <section class="panel pipeline-deck">
      <div class="deck-header">
        <div>
          <span class="deck-eyebrow">PRODUCTION PIPELINE FUNNEL</span>
          <h2 class="deck-title">Active Order Stages (উৎপাদন পর্যায়)</h2>
        </div>
        <button class="deck-link-btn" @click="emit('navigate', 'Orders')">
          Open Full Kanban Board →
        </button>
      </div>

      <div class="pipeline-track">
        <!-- Stage 1: Measuring -->
        <div class="pipeline-node" @click="emit('navigate', 'Orders')">
          <div class="node-icon-wrap node-icon--measuring">📏</div>
          <div class="node-content">
            <span class="node-value">{{ data?.metrics.pipeline?.measuring ?? 0 }}</span>
            <span class="node-title">Measuring</span>
            <small class="node-sub">মাপে আছে</small>
          </div>
        </div>

        <div class="pipeline-connector">➔</div>

        <!-- Stage 2: Cutting Queue -->
        <div class="pipeline-node" @click="emit('navigate', 'Orders')">
          <div class="node-icon-wrap node-icon--cutting">✂</div>
          <div class="node-content">
            <span class="node-value">{{ data?.metrics.pipeline?.pending_assignment ?? 0 }}</span>
            <span class="node-title">Cutting Queue</span>
            <small class="node-sub">কাটিং অপেক্ষমাণ</small>
          </div>
        </div>

        <div class="pipeline-connector">➔</div>

        <!-- Stage 3: In Stitching -->
        <div class="pipeline-node" @click="emit('navigate', 'Orders')">
          <div class="node-icon-wrap node-icon--stitching">🪡</div>
          <div class="node-content">
            <span class="node-value">{{ data?.metrics.pipeline?.in_progress ?? 0 }}</span>
            <span class="node-title">In Stitching</span>
            <small class="node-sub">কারিগর সেলাই</small>
          </div>
        </div>

        <div class="pipeline-connector">➔</div>

        <!-- Stage 4: Ready for Trial/Pickup -->
        <div class="pipeline-node pipeline-node--ready" @click="emit('navigate', 'Orders')">
          <div class="node-icon-wrap node-icon--ready">✨</div>
          <div class="node-content">
            <span class="node-value">{{ data?.metrics.pipeline?.ready ?? 0 }}</span>
            <span class="node-title">Ready for Trial</span>
            <small class="node-sub">বিতরণ প্রস্তুত</small>
          </div>
        </div>

        <div class="pipeline-connector">➔</div>

        <!-- Stage 5: Delivered -->
        <div class="pipeline-node pipeline-node--delivered" @click="emit('navigate', 'Orders')">
          <div class="node-icon-wrap node-icon--delivered">📦</div>
          <div class="node-content">
            <span class="node-value">{{ data?.metrics.pipeline?.delivered ?? 0 }}</span>
            <span class="node-title">Delivered</span>
            <small class="node-sub">সম্পন্ন ডেলিভারি</small>
          </div>
        </div>
      </div>
    </section>

    <!-- 4 PRIMARY LUXURY KPI TILES -->
    <section class="kpi-grid" :aria-busy="loading">
      <!-- 1. Revenue Inflow -->
      <article class="kpi-card kpi-card--revenue" @click="emit('navigate', 'Reports')">
        <div class="kpi-accent-bar"></div>
        <div class="kpi-content">
          <div class="kpi-top">
            <span class="kpi-label">TODAY'S CASH INFLOW</span>
            <span class="kpi-badge">💰 Verified Inflow</span>
          </div>
          <strong class="kpi-number">৳ {{ (((data?.metrics.revenue_minor ?? 0) / 100)).toLocaleString() }}</strong>
          <p class="kpi-desc">Advance making charges & retail fabric sales today</p>
        </div>
        <div class="kpi-footer">
          <span>View cash flow report</span>
          <span class="kpi-arrow">→</span>
        </div>
      </article>

      <!-- 2. In Production -->
      <article class="kpi-card kpi-card--production" @click="emit('navigate', 'Orders')">
        <div class="kpi-accent-bar"></div>
        <div class="kpi-content">
          <div class="kpi-top">
            <span class="kpi-label">IN PRODUCTION</span>
            <span class="kpi-badge">✂ Workshop Floor</span>
          </div>
          <strong class="kpi-number">{{ data?.metrics.in_progress ?? 0 }} <small>garments</small></strong>
          <p class="kpi-desc">Bespoke garments currently in cutting & sewing</p>
        </div>
        <div class="kpi-footer">
          <span>Manage production queue</span>
          <span class="kpi-arrow">→</span>
        </div>
      </article>

      <!-- 3. Ready for Collection -->
      <article class="kpi-card kpi-card--ready" @click="emit('navigate', 'Orders')">
        <div class="kpi-accent-bar"></div>
        <div class="kpi-content">
          <div class="kpi-top">
            <span class="kpi-label">READY FOR PICKUP</span>
            <span class="kpi-badge">✨ Ready & Ironed</span>
          </div>
          <strong class="kpi-number">{{ data?.metrics.ready ?? 0 }} <small>orders</small></strong>
          <p class="kpi-desc">Tailored garments ready for customer trial or pickup</p>
        </div>
        <div class="kpi-footer">
          <span>View pickup list</span>
          <span class="kpi-arrow">→</span>
        </div>
      </article>

      <!-- 4. Due Deadlines / Overdue -->
      <article
        class="kpi-card"
        :class="(data?.metrics.overdue_count || 0) > 0 ? 'kpi-card--overdue' : 'kpi-card--normal'"
        @click="emit('navigate', 'Orders')"
      >
        <div class="kpi-accent-bar"></div>
        <div class="kpi-content">
          <div class="kpi-top">
            <span class="kpi-label">DUE DEADLINES</span>
            <span class="kpi-badge" :class="{ 'kpi-badge--alert': (data?.metrics.overdue_count || 0) > 0 }">
              {{ (data?.metrics.overdue_count || 0) > 0 ? '⚠️ Action Required' : '📅 On Schedule' }}
            </span>
          </div>
          <strong class="kpi-number">{{ (data?.metrics.due_today ?? 0) + (data?.metrics.overdue_count ?? 0) }} <small>due</small></strong>
          <p class="kpi-desc">
            {{ (data?.metrics.overdue_count || 0) > 0 ? `${data?.metrics.overdue_count} orders past promise date` : "All deliveries promised for today on track" }}
          </p>
        </div>
        <div class="kpi-footer">
          <span>Review delivery schedule</span>
          <span class="kpi-arrow">→</span>
        </div>
      </article>
    </section>

    <!-- OPERATIONAL HEALTH PULSE STRIP -->
    <div class="health-strip">
      <div class="health-card" @click="emit('navigate', 'Customers')">
        <div class="health-icon health-icon--gold">💰</div>
        <div class="health-meta">
          <small>Total Customer Receivables (বকেয়া)</small>
          <strong>৳ {{ (((data?.metrics.total_receivables_minor ?? 0) / 100)).toLocaleString() }}</strong>
        </div>
      </div>

      <div class="health-card" @click="emit('navigate', 'Karigars')">
        <div class="health-icon health-icon--emerald">👥</div>
        <div class="health-meta">
          <small>Active Workshop Craftsmen (কারিগর)</small>
          <strong>{{ data?.metrics.craftsmen_count ?? 0 }} Masters & Karigars On Duty</strong>
        </div>
      </div>

      <div class="health-card" @click="emit('navigate', 'Inventory')">
        <div class="health-icon health-icon--coral">🧵</div>
        <div class="health-meta">
          <small>Fabric Inventory Health (মজুত সতর্কতা)</small>
          <strong :class="{ 'alert-text': (data?.metrics.low_stock_count || 0) > 0 }">
            {{ (data?.metrics.low_stock_count || 0) > 0 ? `${data?.metrics.low_stock_count} Fabrics Below Reorder Level` : "All Fabrics in Healthy Stock" }}
          </strong>
        </div>
      </div>
    </div>

    <!-- WORKSHOP QUICK LAUNCHPAD -->
    <section class="panel launchpad-deck">
      <div class="deck-header">
        <div>
          <span class="deck-eyebrow">ONE-CLICK WORKFLOWS</span>
          <h2 class="deck-title">Quick Action Launchpad (দ্রুত কাজ শুরু করুন)</h2>
        </div>
      </div>

      <div class="launchpad-grid">
        <button class="launch-card" @click="emit('navigate', 'Orders')">
          <div class="launch-icon-box"><NavIcon name="Orders" /></div>
          <div class="launch-text">
            <strong>New Bespoke Order</strong>
            <small>Book custom garment, visual measurements & cutting slips</small>
          </div>
          <span class="launch-kbd">F2</span>
        </button>

        <button class="launch-card" @click="emit('navigate', 'Operations')">
          <div class="launch-icon-box launch-icon-box--gold"><NavIcon name="Cash" /></div>
          <div class="launch-text">
            <strong>Fabric Retail POS</strong>
            <small>Direct cloth sales by yard, ready-made outfits & accessories</small>
          </div>
          <span class="launch-kbd">F3</span>
        </button>

        <button class="launch-card" @click="emit('navigate', 'Customers')">
          <div class="launch-icon-box launch-icon-box--blue"><NavIcon name="Customers" /></div>
          <div class="launch-text">
            <strong>Customer Ledger & Dues</strong>
            <small>Collect due balances, running statements & loyalty points</small>
          </div>
          <span class="launch-kbd">Ctrl+K</span>
        </button>

        <button class="launch-card" @click="emit('navigate', 'Reports')">
          <div class="launch-icon-box launch-icon-box--emerald"><NavIcon name="Reports" /></div>
          <div class="launch-text">
            <strong>Cash Flow & Daily P&L</strong>
            <small>Monitor daily income, karigar wages & operating overhead</small>
          </div>
          <span class="launch-kbd">P&L</span>
        </button>
      </div>
    </section>

    <!-- 2-COLUMN SECTION: DUE DELIVERY QUEUE & RECENT WORKSHOP ACTIVITY STREAM -->
    <div class="workspace-dual-deck">
      <!-- LEFT: DELIVERY PROMISES & URGENT QUEUE -->
      <section class="panel delivery-deck">
        <div class="deck-header">
          <div>
            <span class="deck-eyebrow">TODAY'S PROMISES & OVERDUE</span>
            <h2 class="deck-title">Delivery Queue (ডেলিভারি তালিকা)</h2>
          </div>
          <span class="badge-count">{{ data?.due_today_orders?.length || 0 }}</span>
        </div>

        <p v-if="loading" class="loading-state">Loading delivery queue…</p>
        <div v-else-if="!data?.due_today_orders?.length" class="empty-state-card">
          <div class="empty-icon">🎉</div>
          <strong>No pending deliveries promised for today!</strong>
          <p>All workshop orders are completed and scheduled on track.</p>
        </div>

        <div v-else class="delivery-list">
          <article
            v-for="o in data?.due_today_orders || []"
            :key="o.id"
            class="delivery-card"
            :class="{ 'delivery-card--overdue': o.promised_at && new Date(o.promised_at) < new Date() }"
          >
            <div class="delivery-time-pill">
              <span>{{ dueTime(o.promised_at) || 'Today' }}</span>
            </div>

            <div class="delivery-info">
              <div class="delivery-row-top">
                <strong class="customer-name">{{ o.customer.name }}</strong>
                <span class="order-tag">#{{ o.order_number }}</span>
              </div>
              <div class="delivery-row-sub">
                <span class="garment-type">{{ o.garment.name }}</span>
                <span v-if="o.karigar" class="karigar-badge">✂ {{ o.karigar.name }}</span>
                <span v-if="(o.due_minor || 0) > 0" class="due-badge">Due ৳ {{ ((o.due_minor || 0) / 100).toFixed(0) }}</span>
              </div>
            </div>

            <div class="delivery-actions">
              <span class="status" :class="`status--${o.status}`">{{ statusLabel(o.status) }}</span>
              <a
                v-if="o.customer.mobile_number"
                :href="getWhatsAppUrl(o.customer.mobile_number, o.customer.name, o.order_number)"
                target="_blank"
                class="whatsapp-action-btn"
                title="Send WhatsApp notification"
              >
                <NavIcon name="WhatsApp" />
              </a>
            </div>
          </article>
        </div>
      </section>

      <!-- RIGHT: RECENT WORKSHOP ACTIVITY STREAM -->
      <section class="panel activity-deck">
        <div class="deck-header">
          <div>
            <span class="deck-eyebrow">WORKSHOP ACTIVITY FEED</span>
            <h2 class="deck-title">{{ t('workspace.dashboard.recent_orders') }}</h2>
          </div>
          <button class="deck-link-btn" @click="emit('navigate', 'Orders')">
            View All →
          </button>
        </div>

        <p v-if="loading" class="loading-state">Loading recent activity…</p>
        <p v-else-if="!data?.recent_orders.length" class="empty-state">{{ t('workspace.dashboard.no_orders') }}</p>

        <div v-else class="activity-stream">
          <div
            v-for="o in data?.recent_orders || []"
            :key="o.number"
            class="activity-row"
            @click="emit('navigate', 'Orders')"
          >
            <div class="activity-avatar">✂</div>
            <div class="activity-meta">
              <strong class="activity-name">{{ o.customer.name }}</strong>
              <small class="activity-details">#{{ o.order_number || o.number }} · {{ o.garment.name }}</small>
            </div>
            <span class="status" :class="`status--${o.status}`">{{ statusLabel(o.status) }}</span>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.dashboard-root {
  display: flex;
  flex-direction: column;
  gap: 1.75rem;
}

/* ============================================================
   Executive Atelier Welcome Hero Banner
   ============================================================ */
.atelier-hero-banner {
  background: linear-gradient(135deg, #07221b 0%, #0d362b 60%, #124437 100%);
  border: 1px solid rgb(212 175 55 / 25%);
  border-radius: var(--radius-lg);
  padding: 1.75rem 2rem;
  box-shadow: 0 12px 32px rgb(7 34 27 / 20%), 0 2px 6px rgb(0 0 0 / 10%);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1.5rem;
  flex-wrap: wrap;
  position: relative;
  overflow: hidden;
}

.atelier-hero-banner::after {
  content: "";
  position: absolute;
  top: -50%;
  right: -10%;
  width: 25rem;
  height: 25rem;
  background: radial-gradient(circle, rgb(212 175 55 / 12%) 0%, transparent 70%);
  pointer-events: none;
}

.hero-left {
  position: relative;
  z-index: 2;
}

.hero-status-tag {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.725rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: var(--accent-gold);
  background: rgb(212 175 55 / 15%);
  border: 1px solid rgb(212 175 55 / 30%);
  padding: 0.25rem 0.75rem;
  border-radius: var(--radius-pill);
  margin-bottom: 0.5rem;
}

.live-dot-pulse {
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 50%;
  background: #25d366;
  box-shadow: 0 0 8px #25d366;
  animation: pulse-glow 2s infinite;
}

@keyframes pulse-glow {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.4; transform: scale(0.85); }
}

.hero-title {
  margin: 0 0 0.35rem;
  font: 800 1.85rem var(--serif);
  color: #ffffff;
  letter-spacing: -0.01em;
}

.hero-subtitle {
  margin: 0;
  color: rgb(226 240 235 / 80%);
  font-size: 0.92rem;
  max-width: 38rem;
}

.hero-actions {
  display: flex;
  gap: 0.85rem;
  align-items: center;
  position: relative;
  z-index: 2;
}

.hero-btn-primary {
  background: linear-gradient(135deg, var(--accent-gold) 0%, var(--accent) 100%);
  color: #000;
  border: none;
  font-weight: 700;
  font-size: 0.92rem;
  padding: 0.8rem 1.4rem;
  border-radius: var(--radius);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  box-shadow: 0 4px 16px rgb(212 175 55 / 30%);
  transition: all 160ms cubic-bezier(0.16, 1, 0.3, 1);
}

.hero-btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgb(212 175 55 / 45%);
}

.hero-btn-secondary {
  background: rgb(255 255 255 / 10%);
  color: #fff;
  border: 1px solid rgb(255 255 255 / 20%);
  backdrop-filter: blur(8px);
  font-weight: 600;
  font-size: 0.92rem;
  padding: 0.8rem 1.3rem;
  border-radius: var(--radius);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 160ms ease;
}

.hero-btn-secondary:hover {
  background: rgb(255 255 255 / 18%);
  border-color: #fff;
}

/* ============================================================
   Live 5-Stage Production Pipeline Deck
   ============================================================ */
.pipeline-deck {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius-lg);
  padding: 1.5rem;
  box-shadow: var(--shadow-xs);
}

.deck-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
}

.deck-eyebrow {
  display: block;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  color: var(--muted);
  text-transform: uppercase;
}

.deck-title {
  margin: 0.15rem 0 0;
  font: 700 1.25rem var(--serif);
  color: var(--ink);
}

.deck-link-btn {
  background: transparent;
  border: none;
  color: var(--primary);
  font-weight: 700;
  font-size: 0.85rem;
  cursor: pointer;
  transition: color 150ms ease;
}

.deck-link-btn:hover {
  color: var(--primary-hover);
  text-decoration: underline;
}

.pipeline-track {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.6rem;
  overflow-x: auto;
  padding-bottom: 0.5rem;
  -webkit-overflow-scrolling: touch;
}

.pipeline-node {
  flex: 1;
  min-width: 11rem;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  padding: 0.95rem 1rem;
  display: flex;
  align-items: center;
  gap: 0.85rem;
  cursor: pointer;
  transition: all 160ms cubic-bezier(0.16, 1, 0.3, 1);
}

.pipeline-node:hover {
  background: var(--surface);
  border-color: var(--primary);
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
}

.node-icon-wrap {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-sm);
  display: grid;
  place-items: center;
  font-size: 1.25rem;
  flex-shrink: 0;
}

.node-icon--measuring { background: var(--status-measuring-soft); color: var(--status-measuring); }
.node-icon--cutting { background: var(--status-pending-soft); color: var(--status-pending); }
.node-icon--stitching { background: var(--status-inprogress-soft); color: var(--status-inprogress); }
.node-icon--ready { background: var(--status-ready-soft); color: var(--status-ready); }
.node-icon--delivered { background: var(--status-delivered-soft); color: var(--status-delivered); }

.node-content {
  display: flex;
  flex-direction: column;
}

.node-value {
  font-size: 1.4rem;
  font-weight: 800;
  color: var(--ink);
  line-height: 1.1;
}

.node-title {
  font-size: 0.825rem;
  font-weight: 700;
  color: var(--ink);
  margin-top: 0.1rem;
}

.node-sub {
  font-size: 0.725rem;
  color: var(--muted);
}

.pipeline-connector {
  color: var(--line-strong);
  font-weight: 700;
  font-size: 0.9rem;
}

/* ============================================================
   4 Primary Luxury KPI Cards
   ============================================================ */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.25rem;
}

.kpi-card {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  box-shadow: var(--shadow-xs);
  cursor: pointer;
  transition: all 160ms cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
  overflow: hidden;
}

.kpi-card:hover {
  transform: translateY(-3px);
  border-color: var(--primary);
  box-shadow: var(--shadow);
}

.kpi-accent-bar {
  height: 4px;
  width: 100%;
}

.kpi-card--revenue .kpi-accent-bar { background: linear-gradient(90deg, var(--accent-gold), var(--accent)); }
.kpi-card--production .kpi-accent-bar { background: linear-gradient(90deg, var(--status-inprogress), #e5a740); }
.kpi-card--ready .kpi-accent-bar { background: linear-gradient(90deg, #117343, #25d366); }
.kpi-card--overdue .kpi-accent-bar { background: linear-gradient(90deg, #a32215, #e53935); }
.kpi-card--normal .kpi-accent-bar { background: linear-gradient(90deg, var(--primary), var(--line-strong)); }

.kpi-content {
  padding: 1.25rem 1.25rem 0.75rem;
}

.kpi-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.kpi-label {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: var(--muted);
}

.kpi-badge {
  font-size: 0.7rem;
  font-weight: 700;
  padding: 0.15rem 0.5rem;
  border-radius: var(--radius-pill);
  background: var(--paper);
  border: 1px solid var(--line);
  color: var(--ink-secondary);
}

.kpi-badge--alert {
  background: var(--status-cancelled-soft);
  color: var(--status-cancelled);
  border-color: var(--status-cancelled);
}

.kpi-number {
  display: block;
  font-size: 1.85rem;
  font-weight: 800;
  color: var(--ink);
  line-height: 1.1;
}

.kpi-number small {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--muted);
}

.kpi-card--revenue .kpi-number { color: var(--accent); }
.kpi-card--production .kpi-number { color: var(--status-inprogress); }
.kpi-card--ready .kpi-number { color: var(--status-ready); }
.kpi-card--overdue .kpi-number { color: var(--status-cancelled); }

.kpi-desc {
  margin: 0.35rem 0 0;
  font-size: 0.78rem;
  color: var(--muted);
  line-height: 1.35;
}

.kpi-footer {
  padding: 0.65rem 1.25rem;
  border-top: 1px solid var(--line);
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--primary);
  background: var(--paper);
}

.kpi-arrow {
  transition: transform 150ms ease;
}

.kpi-card:hover .kpi-arrow {
  transform: translateX(3px);
}

/* ============================================================
   Operational Health Pulse Strip
   ============================================================ */
.health-strip {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.25rem;
}

.health-card {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  padding: 1rem 1.25rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: var(--shadow-xs);
  cursor: pointer;
  transition: all 150ms ease;
}

.health-card:hover {
  border-color: var(--primary);
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
}

.health-icon {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-sm);
  display: grid;
  place-items: center;
  font-size: 1.35rem;
  flex-shrink: 0;
}

.health-icon--gold { background: var(--accent-soft); }
.health-icon--emerald { background: var(--primary-soft); }
.health-icon--coral { background: var(--status-cancelled-soft); }

.health-meta small {
  display: block;
  font-size: 0.75rem;
  color: var(--muted);
  font-weight: 600;
}

.health-meta strong {
  display: block;
  font-size: 1.05rem;
  color: var(--ink);
  margin-top: 0.15rem;
}

/* ============================================================
   Workshop Quick Launchpad Deck
   ============================================================ */
.launchpad-deck {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius-lg);
  padding: 1.5rem;
  box-shadow: var(--shadow-xs);
}

.launchpad-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
}

.launch-card {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  padding: 1.25rem;
  text-align: left;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  cursor: pointer;
  transition: all 160ms cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
}

.launch-card:hover {
  background: var(--surface);
  border-color: var(--primary);
  box-shadow: var(--shadow-sm);
  transform: translateY(-2px);
}

.launch-icon-box {
  width: 2.6rem;
  height: 2.6rem;
  border-radius: var(--radius-sm);
  background: var(--primary-soft);
  color: var(--primary);
  display: grid;
  place-items: center;
  font-size: 1.15rem;
}

.launch-icon-box--gold { background: var(--accent-soft); color: var(--accent); }
.launch-icon-box--blue { background: var(--status-measuring-soft); color: var(--status-measuring); }
.launch-icon-box--emerald { background: var(--status-ready-soft); color: var(--status-ready); }

.launch-text strong {
  display: block;
  font-size: 0.95rem;
  color: var(--ink);
}

.launch-text small {
  display: block;
  font-size: 0.78rem;
  color: var(--muted);
  line-height: 1.35;
  margin-top: 0.2rem;
}

.launch-kbd {
  position: absolute;
  top: 1rem;
  right: 1rem;
  font-size: 0.68rem;
  font-weight: 700;
  color: var(--muted);
  background: var(--surface);
  border: 1px solid var(--line);
  padding: 0.15rem 0.4rem;
  border-radius: 4px;
}

/* ============================================================
   Dual Deck Layout: Delivery Queue & Workshop Activity
   ============================================================ */
.workspace-dual-deck {
  display: grid;
  grid-template-columns: 1.15fr 0.85fr;
  gap: 1.5rem;
  align-items: start;
}

@media (max-width: 64rem) {
  .workspace-dual-deck {
    grid-template-columns: 1fr;
  }
}

.delivery-deck,
.activity-deck {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius-lg);
  padding: 1.5rem;
  box-shadow: var(--shadow-xs);
}

.badge-count {
  background: var(--paper);
  border: 1px solid var(--line);
  padding: 0.2rem 0.65rem;
  border-radius: var(--radius-pill);
  font-size: 0.8rem;
  font-weight: 700;
}

.delivery-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.delivery-card {
  display: grid;
  grid-template-columns: 4.5rem 1fr auto;
  gap: 0.85rem;
  align-items: center;
  padding: 0.9rem 1rem;
  background: var(--paper);
  border-radius: var(--radius-sm);
  border: 1px solid var(--line);
  transition: border-color 150ms ease;
}

.delivery-card:hover {
  border-color: var(--line-strong);
  background: var(--surface);
}

.delivery-card--overdue {
  background: var(--status-cancelled-soft);
  border-color: var(--status-cancelled);
}

.delivery-time-pill {
  font-weight: 800;
  font-size: 0.8rem;
  color: var(--muted);
  background: var(--surface);
  border: 1px solid var(--line);
  padding: 0.35rem 0.5rem;
  border-radius: var(--radius-xs);
  text-align: center;
}

.delivery-row-top {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.customer-name {
  font-size: 0.92rem;
  color: var(--ink);
}

.order-tag {
  font-size: 0.725rem;
  font-weight: 700;
  background: var(--surface);
  border: 1px solid var(--line);
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
}

.delivery-row-sub {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.78rem;
  color: var(--muted);
  margin-top: 0.2rem;
  flex-wrap: wrap;
}

.karigar-badge {
  background: var(--surface);
  border: 1px solid var(--line);
  padding: 0.1rem 0.4rem;
  border-radius: 3px;
  font-weight: 600;
}

.due-badge {
  color: var(--status-cancelled);
  font-weight: 700;
}

.delivery-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.whatsapp-action-btn {
  color: #25d366;
  padding: 0.4rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-xs);
  background: rgb(37 211 102 / 12%);
  transition: all 150ms ease;
}

.whatsapp-action-btn:hover {
  background: #25d366;
  color: #fff;
}


/* ============================================================
   Recent Activity Stream
   ============================================================ */
.activity-stream {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

.activity-row {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 0.85rem 1.15rem;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: all 150ms ease;
}

.activity-row:hover {
  border-color: var(--primary);
  background: var(--surface);
  transform: translateX(2px);
}

.activity-avatar {
  width: 2.4rem;
  height: 2.4rem;
  border-radius: 50%;
  background: var(--surface);
  border: 1px solid var(--line);
  display: grid;
  place-items: center;
  color: var(--primary);
  font-size: 1.1rem;
  flex-shrink: 0;
}

.activity-meta {
  flex: 1;
}

.activity-name {
  display: block;
  font-size: 0.92rem;
  color: var(--ink);
}

.activity-details {
  color: var(--muted);
  font-size: 0.78rem;
}

.empty-state-card {
  padding: 2.5rem 1rem;
  text-align: center;
  background: var(--paper);
  border: 1px dashed var(--line-strong);
  border-radius: var(--radius);
}

.empty-icon {
  font-size: 2rem;
  margin-bottom: 0.5rem;
}

.empty-state-card strong {
  display: block;
  font-size: 0.95rem;
  color: var(--ink);
}

.empty-state-card p {
  margin: 0.25rem 0 0;
  font-size: 0.825rem;
  color: var(--muted);
}

/* ============================================================
   Responsive Adaptations
   ============================================================ */
@media (max-width: 72rem) {
  .kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .launchpad-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 56rem) {
  .workspace-dual-deck {
    grid-template-columns: 1fr;
  }
  .health-strip {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 48rem) {
  .atelier-hero-banner {
    flex-direction: column;
    align-items: flex-start;
    padding: 1.5rem 1.25rem;
  }
  .hero-actions {
    width: 100%;
  }
  .hero-btn-primary,
  .hero-btn-secondary {
    flex: 1;
    justify-content: center;
  }
  .kpi-grid {
    grid-template-columns: 1fr;
  }
  .launchpad-grid {
    grid-template-columns: 1fr;
  }
  .delivery-card {
    grid-template-columns: 1fr;
    gap: 0.5rem;
  }
  .delivery-actions {
    justify-content: flex-start;
  }
  .pipeline-connector {
    display: none;
  }
}
</style>
