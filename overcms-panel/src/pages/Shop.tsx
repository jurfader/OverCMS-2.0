import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  ShoppingCart, Package, TrendingUp, Clock, CheckCircle2,
  XCircle, AlertCircle, RefreshCw, ExternalLink, X,
  Loader2, ChevronDown, Plus, CreditCard, Truck, Receipt,
  Mail, Settings2, Tag, BarChart3, Users,
} from 'lucide-react';
import { api } from '@/lib/api';
import { boot, type WcOrder, type WcOrderStatus, type WcProduct } from '@/lib/types';
import { buildEmbedUrl, clearEmbedCookie } from '@/lib/embed';
import { PageHeader } from '@/components/layout/Shell';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';

// ─── Status helpers ───────────────────────────────────────────────────────────

const STATUS_LABELS: Record<WcOrderStatus, string> = {
  pending:    'Oczekuje na płatność',
  processing: 'W realizacji',
  'on-hold':  'Wstrzymane',
  completed:  'Zrealizowane',
  cancelled:  'Anulowane',
  refunded:   'Zwrócone',
  failed:     'Nieudane',
};

const STATUS_VARIANT: Record<WcOrderStatus, 'default' | 'success' | 'warning' | 'destructive' | 'outline'> = {
  pending:    'warning',
  processing: 'default',
  'on-hold':  'warning',
  completed:  'success',
  cancelled:  'destructive',
  refunded:   'outline',
  failed:     'destructive',
};

const ALL_STATUSES: WcOrderStatus[] = ['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed'];

function fmtPrice(amount: string, currency: string) {
  try {
    return new Intl.NumberFormat('pl-PL', { style: 'currency', currency }).format(Number(amount));
  } catch {
    return `${amount} ${currency}`;
  }
}

// ─── Page ─────────────────────────────────────────────────────────────────────

type Tab = 'orders' | 'products' | 'config';

export function ShopPage() {
  const [tab, setTab]           = useState<Tab>('orders');
  const [embedUrl, setEmbedUrl] = useState<string | null>(null);
  const [embedTitle, setEmbedTitle] = useState('WooCommerce');
  const adminUrl = boot.adminUrl.replace(/\/?$/, '/');

  function openEmbed(url: string, title = 'WooCommerce') {
    setEmbedTitle(title);
    setEmbedUrl(buildEmbedUrl(url));
  }
  function closeEmbed() {
    clearEmbedCookie();
    setEmbedUrl(null);
  }

  const tabs: { key: Tab; label: string; icon: React.ReactNode }[] = [
    { key: 'orders',   label: 'Zamówienia',  icon: <ShoppingCart className="w-3.5 h-3.5" /> },
    { key: 'products', label: 'Produkty',    icon: <Package className="w-3.5 h-3.5" /> },
    { key: 'config',   label: 'Konfiguracja', icon: <Settings2 className="w-3.5 h-3.5" /> },
  ];

  return (
    <>
      <PageHeader
        title="Sklep"
        description="Zamówienia, produkty i konfiguracja WooCommerce."
        actions={
          <Button
            variant="outline"
            icon={<ExternalLink className="w-3.5 h-3.5" />}
            onClick={() => openEmbed(`${adminUrl}admin.php?page=wc-admin`, 'WooCommerce')}
          >
            Panel WooCommerce
          </Button>
        }
      />

      {/* Stats */}
      <ShopStats />

      {/* Tabs */}
      <div className="flex items-center gap-1 mb-4 border-b border-[var(--color-border)]">
        {tabs.map((t) => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
            className={`px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px flex items-center gap-1.5 ${
              tab === t.key
                ? 'border-[var(--color-primary)] text-[var(--color-primary)]'
                : 'border-transparent text-[var(--color-muted-foreground)] hover:text-[var(--color-foreground)]'
            }`}
          >
            {t.icon}
            {t.label}
          </button>
        ))}
      </div>

      {tab === 'orders'   && <OrdersTab   onOpenOrder={(id)   => openEmbed(`${adminUrl}post.php?post=${id}&action=edit`, 'Zamówienie')} />}
      {tab === 'products' && <ProductsTab onOpenProduct={(id) => openEmbed(`${adminUrl}post.php?post=${id}&action=edit`, 'Produkt')} onAddProduct={() => openEmbed(`${adminUrl}post-new.php?post_type=product`, 'Nowy produkt')} />}
      {tab === 'config'   && <ConfigTab   onOpen={openEmbed} adminUrl={adminUrl} />}

      {/* Iframe overlay */}
      {embedUrl && (
        <div className="fixed inset-0 z-50 flex flex-col bg-[var(--color-background)]">
          <div className="h-10 flex items-center justify-between px-4 border-b border-[var(--color-border)] bg-[var(--color-surface)] shrink-0">
            <span className="text-xs font-medium text-[var(--color-foreground)]">{embedTitle}</span>
            <button
              onClick={closeEmbed}
              className="w-7 h-7 flex items-center justify-center rounded-[var(--radius)] hover:bg-[var(--color-surface-elevated)] text-[var(--color-muted-foreground)] transition-colors"
              aria-label="Zamknij"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
          <iframe src={embedUrl} className="flex-1 w-full border-0" title={embedTitle} />
        </div>
      )}
    </>
  );
}

// ─── Stats row ────────────────────────────────────────────────────────────────

function ShopStats() {
  const { data: orders } = useQuery({
    queryKey: ['wc-orders-all'],
    queryFn: () => api<WcOrder[]>('wc/v3/orders', { query: { per_page: 100, status: 'any' } }),
  });
  const { data: products } = useQuery({
    queryKey: ['wc-products-count'],
    queryFn: () => api<WcProduct[]>('wc/v3/products', { query: { per_page: 1 } }),
  });

  const today = new Date().toISOString().slice(0, 10);
  const processing = orders?.filter((o) => o.status === 'processing') ?? [];
  const todayOrders = orders?.filter((o) => o.date_created.slice(0, 10) === today) ?? [];
  const revenue7d = orders
    ?.filter((o) => {
      const d = new Date(o.date_created);
      const cutoff = new Date(); cutoff.setDate(cutoff.getDate() - 7);
      return d >= cutoff && o.status !== 'cancelled' && o.status !== 'refunded' && o.status !== 'failed';
    })
    .reduce((sum, o) => sum + Number(o.total), 0) ?? 0;

  const currency = orders?.[0]?.currency ?? 'PLN';

  const stats = [
    { icon: <TrendingUp className="w-5 h-5" />, label: 'Przychód (7 dni)', value: fmtPrice(revenue7d.toFixed(2), currency), color: 'text-[var(--color-success)]' },
    { icon: <Clock className="w-5 h-5" />, label: 'W realizacji', value: processing.length, color: 'text-[var(--color-primary)]' },
    { icon: <ShoppingCart className="w-5 h-5" />, label: 'Dzisiaj', value: todayOrders.length, color: 'text-[var(--color-warning)]' },
    { icon: <Package className="w-5 h-5" />, label: 'Produktów', value: products !== undefined ? '—' : '…', color: 'text-[var(--color-muted-foreground)]' },
  ];

  return (
    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
      {stats.map((s) => (
        <Card key={s.label} className="flex items-center gap-3 py-3">
          <span className={`shrink-0 ${s.color}`}>{s.icon}</span>
          <div className="min-w-0">
            <p className="text-[11px] text-[var(--color-subtle)] truncate">{s.label}</p>
            <p className="text-base font-semibold text-[var(--color-foreground)]">{s.value}</p>
          </div>
        </Card>
      ))}
    </div>
  );
}

// ─── Orders tab ───────────────────────────────────────────────────────────────

function OrdersTab({ onOpenOrder }: { onOpenOrder: (id: number) => void }) {
  const qc = useQueryClient();
  const [statusFilter, setStatusFilter] = useState<WcOrderStatus | 'any'>('any');
  const [changingId, setChangingId] = useState<number | null>(null);

  const { data: orders, isLoading, refetch, isFetching } = useQuery({
    queryKey: ['wc-orders', statusFilter],
    queryFn: () => api<WcOrder[]>('wc/v3/orders', {
      query: { per_page: 50, status: statusFilter === 'any' ? undefined : statusFilter },
    }),
  });

  const setStatus = useMutation({
    mutationFn: ({ id, status }: { id: number; status: WcOrderStatus }) =>
      api(`wc/v3/orders/${id}`, { method: 'PUT', body: { status } }),
    onMutate: ({ id }) => setChangingId(id),
    onSettled: () => {
      setChangingId(null);
      qc.invalidateQueries({ queryKey: ['wc-orders'] });
      qc.invalidateQueries({ queryKey: ['wc-orders-all'] });
    },
  });

  return (
    <div>
      {/* Filtry */}
      <div className="flex items-center justify-between gap-3 mb-3 flex-wrap">
        <div className="flex flex-wrap gap-1.5">
          <StatusPill active={statusFilter === 'any'} onClick={() => setStatusFilter('any')} label="Wszystkie" />
          {(['pending', 'processing', 'on-hold', 'completed', 'cancelled'] as WcOrderStatus[]).map((s) => (
            <StatusPill key={s} active={statusFilter === s} onClick={() => setStatusFilter(s)} label={STATUS_LABELS[s]} />
          ))}
        </div>
        <button
          onClick={() => refetch()}
          className="flex items-center gap-1.5 text-xs text-[var(--color-muted-foreground)] hover:text-[var(--color-foreground)] transition-colors"
        >
          <RefreshCw className={`w-3.5 h-3.5 ${isFetching ? 'animate-spin' : ''}`} />
          Odśwież
        </button>
      </div>

      {isLoading && (
        <div className="flex items-center gap-2 justify-center py-12 text-sm text-[var(--color-muted-foreground)]">
          <Loader2 className="w-4 h-4 animate-spin" /> Ładowanie…
        </div>
      )}

      {!isLoading && orders?.length === 0 && (
        <div className="glass-card rounded-[var(--radius-lg)] py-12 text-center text-sm text-[var(--color-muted-foreground)]">
          Brak zamówień
        </div>
      )}

      {orders && orders.length > 0 && (
        <div className="glass-card rounded-[var(--radius-lg)] overflow-hidden">
          <div className="grid grid-cols-[80px_1fr_180px_140px_120px_auto] px-5 py-2.5 border-b border-[var(--color-border)] bg-[var(--color-surface-elevated)]">
            {['#', 'Klient', 'Status', 'Data', 'Kwota', ''].map((h) => (
              <span key={h} className="text-[10px] font-semibold uppercase tracking-widest text-[var(--color-subtle)]">{h}</span>
            ))}
          </div>
          <div className="divide-y divide-[var(--color-border)]">
            {orders.map((order) => (
              <OrderRow
                key={order.id}
                order={order}
                isChanging={changingId === order.id}
                onOpen={() => onOpenOrder(order.id)}
                onStatusChange={(status) => setStatus.mutate({ id: order.id, status })}
              />
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

function StatusPill({ active, onClick, label }: { active: boolean; onClick: () => void; label: string }) {
  return (
    <button
      onClick={onClick}
      className={`px-3 py-1 rounded-full text-[11px] font-medium transition-colors ${
        active
          ? 'bg-[var(--color-primary)] text-white'
          : 'bg-[var(--color-surface-elevated)] text-[var(--color-muted-foreground)] hover:text-[var(--color-foreground)]'
      }`}
    >
      {label}
    </button>
  );
}

function OrderRow({ order, isChanging, onOpen, onStatusChange }: {
  order: WcOrder;
  isChanging: boolean;
  onOpen: () => void;
  onStatusChange: (s: WcOrderStatus) => void;
}) {
  const [open, setOpen] = useState(false);
  const name = [order.billing.first_name, order.billing.last_name].filter(Boolean).join(' ') || order.billing.email;

  return (
    <div className="grid grid-cols-[80px_1fr_180px_140px_120px_auto] items-center px-5 py-3 hover:bg-[var(--color-surface-elevated)] transition-colors">
      <button
        onClick={onOpen}
        className="text-xs font-mono text-[var(--color-primary)] hover:underline text-left"
      >
        #{order.number}
      </button>
      <div className="min-w-0 pr-3">
        <p className="text-sm text-[var(--color-foreground)] truncate">{name}</p>
        <p className="text-[10px] text-[var(--color-subtle)] truncate">{order.billing.email}</p>
      </div>

      {/* Status z dropdown */}
      <div className="relative">
        <button
          onClick={() => setOpen((v) => !v)}
          className="flex items-center gap-1"
          disabled={isChanging}
        >
          {isChanging
            ? <Loader2 className="w-3.5 h-3.5 animate-spin text-[var(--color-muted-foreground)]" />
            : <Badge variant={STATUS_VARIANT[order.status]}>{STATUS_LABELS[order.status]}</Badge>
          }
          <ChevronDown className="w-3 h-3 text-[var(--color-subtle)]" />
        </button>
        {open && (
          <>
            <div className="fixed inset-0 z-10" onClick={() => setOpen(false)} />
            <div className="absolute left-0 top-full mt-1 z-20 glass-card rounded-[var(--radius)] py-1 min-w-[180px] shadow-lg border border-[var(--color-border)]">
              {ALL_STATUSES.map((s) => (
                <button
                  key={s}
                  onClick={() => { setOpen(false); if (s !== order.status) onStatusChange(s); }}
                  className={`w-full px-3 py-1.5 text-left text-xs transition-colors hover:bg-[var(--color-surface-elevated)] ${s === order.status ? 'text-[var(--color-primary)] font-medium' : 'text-[var(--color-foreground)]'}`}
                >
                  {STATUS_LABELS[s]}
                </button>
              ))}
            </div>
          </>
        )}
      </div>

      <span className="text-xs text-[var(--color-muted-foreground)]">
        {new Date(order.date_created).toLocaleDateString('pl-PL')}
      </span>
      <span className="text-sm font-semibold text-[var(--color-foreground)]">
        {fmtPrice(order.total, order.currency)}
      </span>
      <Button size="icon" variant="ghost" title="Otwórz w WooCommerce" onClick={onOpen}>
        <ExternalLink className="w-3.5 h-3.5" />
      </Button>
    </div>
  );
}

// ─── Products tab ─────────────────────────────────────────────────────────────

function ProductsTab({ onOpenProduct, onAddProduct }: { onOpenProduct: (id: number) => void; onAddProduct: () => void }) {
  const { data: products, isLoading } = useQuery({
    queryKey: ['wc-products'],
    queryFn: () => api<WcProduct[]>('wc/v3/products', { query: { per_page: 50, status: 'any' } }),
  });

  return (
    <div>
      <div className="flex justify-end mb-3">
        <Button icon={<Plus />} onClick={onAddProduct}>
          Dodaj produkt
        </Button>
      </div>

      {isLoading && (
        <div className="flex items-center gap-2 justify-center py-12 text-sm text-[var(--color-muted-foreground)]">
          <Loader2 className="w-4 h-4 animate-spin" /> Ładowanie…
        </div>
      )}

      {!isLoading && products?.length === 0 && (
        <div className="glass-card rounded-[var(--radius-lg)] py-12 text-center">
          <Package className="w-10 h-10 text-[var(--color-subtle)] mx-auto mb-3" />
          <p className="text-sm text-[var(--color-muted-foreground)] mb-4">Brak produktów w sklepie</p>
          <Button icon={<Plus />} onClick={onAddProduct}>Dodaj pierwszy produkt</Button>
        </div>
      )}

      {products && products.length > 0 && (
        <div className="glass-card rounded-[var(--radius-lg)] overflow-hidden">
          <div className="grid grid-cols-[48px_1fr_160px_120px_120px_auto] px-5 py-2.5 border-b border-[var(--color-border)] bg-[var(--color-surface-elevated)]">
            {['', 'Nazwa', 'Kategorie', 'Cena', 'Stan', ''].map((h) => (
              <span key={h} className="text-[10px] font-semibold uppercase tracking-widest text-[var(--color-subtle)]">{h}</span>
            ))}
          </div>
          <div className="divide-y divide-[var(--color-border)]">
            {products.map((p) => (
              <div
                key={p.id}
                className="grid grid-cols-[48px_1fr_160px_120px_120px_auto] items-center px-5 py-3 hover:bg-[var(--color-surface-elevated)] transition-colors"
              >
                {/* Miniatura */}
                <div className="w-9 h-9 rounded-[var(--radius)] bg-[var(--color-surface-elevated)] overflow-hidden flex items-center justify-center shrink-0">
                  {p.images[0] ? (
                    <img src={p.images[0].src} alt={p.name} className="w-full h-full object-cover" />
                  ) : (
                    <Package className="w-4 h-4 text-[var(--color-subtle)]" />
                  )}
                </div>

                <button
                  onClick={() => onOpenProduct(p.id)}
                  className="text-sm text-[var(--color-foreground)] font-medium text-left truncate hover:text-[var(--color-primary)] px-3"
                >
                  {p.name}
                </button>

                <div className="flex flex-wrap gap-1 pr-2">
                  {p.categories.slice(0, 2).map((c) => (
                    <span key={c.id} className="text-[10px] px-2 py-0.5 rounded-full bg-[var(--color-surface-elevated)] text-[var(--color-muted-foreground)]">
                      {c.name}
                    </span>
                  ))}
                </div>

                <div>
                  {p.sale_price ? (
                    <div className="flex items-baseline gap-1.5">
                      <span className="text-sm font-semibold text-[var(--color-success)]">{p.sale_price} zł</span>
                      <span className="text-[10px] text-[var(--color-subtle)] line-through">{p.regular_price} zł</span>
                    </div>
                  ) : (
                    <span className="text-sm font-semibold text-[var(--color-foreground)]">
                      {p.price ? `${p.price} zł` : '—'}
                    </span>
                  )}
                </div>

                <StockBadge status={p.stock_status} qty={p.stock_quantity} />

                <Button size="icon" variant="ghost" title="Edytuj produkt" onClick={() => onOpenProduct(p.id)}>
                  <ExternalLink className="w-3.5 h-3.5" />
                </Button>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

// ─── Config tab ───────────────────────────────────────────────────────────────

function ConfigTab({ onOpen, adminUrl }: { onOpen: (url: string, title: string) => void; adminUrl: string }) {
  const sections = [
    {
      label: 'Sklep',
      items: [
        { icon: <Settings2 className="w-4 h-4" />, title: 'Ogólne', desc: 'Waluta, lokalizacja, podatki', url: `${adminUrl}admin.php?page=wc-settings&tab=general` },
        { icon: <CreditCard className="w-4 h-4" />, title: 'Płatności', desc: 'Metody płatności, bramki', url: `${adminUrl}admin.php?page=wc-settings&tab=checkout`, highlight: true },
        { icon: <Truck className="w-4 h-4" />, title: 'Wysyłka', desc: 'Strefy, metody, koszty dostawy', url: `${adminUrl}admin.php?page=wc-settings&tab=shipping`, highlight: true },
        { icon: <Receipt className="w-4 h-4" />, title: 'Podatki', desc: 'Stawki VAT, klasy podatkowe', url: `${adminUrl}admin.php?page=wc-settings&tab=tax` },
        { icon: <Mail className="w-4 h-4" />, title: 'E-maile', desc: 'Szablony powiadomień', url: `${adminUrl}admin.php?page=wc-settings&tab=email` },
      ],
    },
    {
      label: 'Sprzedaż',
      items: [
        { icon: <Tag className="w-4 h-4" />, title: 'Kupony', desc: 'Kody rabatowe i promocje', url: `${adminUrl}edit.php?post_type=shop_coupon` },
        { icon: <BarChart3 className="w-4 h-4" />, title: 'Raporty', desc: 'Sprzedaż, produkty, kategorie', url: `${adminUrl}admin.php?page=wc-reports` },
        { icon: <Users className="w-4 h-4" />, title: 'Klienci', desc: 'Lista klientów i historia', url: `${adminUrl}admin.php?page=wc-admin&path=/customers` },
      ],
    },
    {
      label: 'Produkty',
      items: [
        { icon: <Package className="w-4 h-4" />, title: 'Kategorie produktów', desc: 'Drzewko kategorii', url: `${adminUrl}edit-tags.php?taxonomy=product_cat&post_type=product` },
        { icon: <Tag className="w-4 h-4" />, title: 'Tagi produktów', desc: 'Etykiety i tagi', url: `${adminUrl}edit-tags.php?taxonomy=product_tag&post_type=product` },
        { icon: <Settings2 className="w-4 h-4" />, title: 'Atrybuty', desc: 'Rozmiary, kolory, warianty', url: `${adminUrl}edit.php?post_type=product&page=product_attributes` },
      ],
    },
  ];

  return (
    <div className="space-y-6">
      {sections.map((section) => (
        <div key={section.label}>
          <p className="text-xs uppercase tracking-widest text-[var(--color-subtle)] mb-3">{section.label}</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            {section.items.map((item) => (
              <button
                key={item.title}
                onClick={() => onOpen(item.url, item.title)}
                className={`glass-card rounded-[var(--radius-lg)] p-4 flex items-start gap-3 text-left hover:bg-[var(--color-surface-elevated)] transition-colors group ${
                  item.highlight ? 'ring-1 ring-[var(--color-primary)]/30' : ''
                }`}
              >
                <span className={`w-9 h-9 rounded-[var(--radius)] flex items-center justify-center shrink-0 ${
                  item.highlight
                    ? 'gradient-bg text-white'
                    : 'bg-[var(--color-surface-elevated)] text-[var(--color-primary)]'
                }`}>
                  {item.icon}
                </span>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-semibold text-[var(--color-foreground)]">{item.title}</p>
                  <p className="text-[11px] text-[var(--color-muted-foreground)] mt-0.5">{item.desc}</p>
                </div>
                <ExternalLink className="w-3.5 h-3.5 text-[var(--color-subtle)] group-hover:text-[var(--color-primary)] transition-colors shrink-0 mt-0.5" />
              </button>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}

function StockBadge({ status, qty }: { status: string; qty: number | null }) {
  if (status === 'instock') {
    return (
      <div className="flex items-center gap-1 text-[var(--color-success)]">
        <CheckCircle2 className="w-3.5 h-3.5 shrink-0" />
        <span className="text-xs">{qty !== null ? `${qty} szt.` : 'Dostępny'}</span>
      </div>
    );
  }
  if (status === 'outofstock') {
    return (
      <div className="flex items-center gap-1 text-[var(--color-destructive)]">
        <XCircle className="w-3.5 h-3.5 shrink-0" />
        <span className="text-xs">Brak</span>
      </div>
    );
  }
  return (
    <div className="flex items-center gap-1 text-[var(--color-warning)]">
      <AlertCircle className="w-3.5 h-3.5 shrink-0" />
      <span className="text-xs">Zamówienie</span>
    </div>
  );
}
