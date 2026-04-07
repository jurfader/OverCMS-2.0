export type Theme = 'dark' | 'light';

export interface OvercmsBoot {
  version: string;
  restRoot: string;
  restNonce: string;
  adminUrl: string;
  siteUrl: string;
  siteTitle: string;
  currentUser: {
    id: number;
    name: string;
    email: string;
    roles: string[];
    avatarUrl: string;
  };
  capabilities: {
    manageOptions: boolean;
    editPages: boolean;
    editPosts: boolean;
    uploadFiles: boolean;
    listUsers: boolean;
  };
  logoutUrl: string;
}

declare global {
  interface Window {
    OVERCMS_BOOT?: OvercmsBoot;
  }
}

export const boot: OvercmsBoot = window.OVERCMS_BOOT ?? {
  version: 'dev',
  restRoot: '/wp-json/',
  restNonce: '',
  adminUrl: '/wp-admin/',
  siteUrl: '/',
  siteTitle: 'OverCMS',
  currentUser: { id: 0, name: 'Dev', email: '', roles: [], avatarUrl: '' },
  capabilities: {
    manageOptions: true,
    editPages: true,
    editPosts: true,
    uploadFiles: true,
    listUsers: true,
  },
  logoutUrl: '/login?action=logout',
};

export interface DashboardStats {
  pages: number;
  pagesAll: number;
  posts: number;
  postsAll: number;
  media: number;
  users: number;
}

export interface DashboardRecentItem {
  id: number;
  title: string;
  type: string;
  status: string;
  modifiedAt: string;
  editUrl: string;
}

export interface DashboardResponse {
  stats: DashboardStats;
  recent: DashboardRecentItem[];
  wpVersion: string;
}

export interface WpPage {
  id: number;
  date: string;
  modified: string;
  slug: string;
  status: 'publish' | 'draft' | 'pending' | 'private' | 'future';
  link: string;
  title: { rendered: string };
  excerpt: { rendered: string };
}

export interface MediaItem {
  id: number;
  title: string;
  mime: string;
  date: string;
  thumb: string | null;
  url: string;
  width: number | null;
  height: number | null;
  sizeBytes: number | null;
}

export interface MediaResponse {
  items: MediaItem[];
  total: number;
  totalPages: number;
  page: number;
}

export interface SiteSettings {
  title: string;
  description: string;
  siteUrl: string;
  adminEmail: string;
  language: string;
  timezone: string;
  permalinks: string;
  theme: Theme;
  wpVersion: string;
  phpVersion: string;
}

export interface ModuleItem {
  id: string;
  file: string;
  name: string;
  description: string;
  version: string;
  author: string;
  pluginUri: string;
  active: boolean;
}

export interface ModulesResponse {
  modules: ModuleItem[];
  installNewUrl: string;
}

export interface WpUser {
  id: number;
  name: string;
  slug: string;
  email?: string;
  roles?: string[];
  avatar_urls: Record<string, string>;
}

export interface MarketplacePlugin {
  slug: string;
  name: string;
  shortDescription: string;
  author: string;
  version: string | null;
  rating: number | null;
  numRatings: number;
  activeInstalls: number;
  icon: string | null;
  iconHigh: string | null;
  lastUpdated: string | null;
  requiresPhp: string | null;
  requiresWp: string | null;
  testedWp: string | null;
  installed: boolean;
  active: boolean;
}

export interface MarketplaceResponse {
  items: MarketplacePlugin[];
  info: {
    page: number;
    pages: number;
    results: number;
  };
}

// ── Templates ───────────────────────────────────────────────
export interface DiviTemplate {
  id: number;
  title: string;
  slug: string;
  type: string;
  builtFor: string;
  thumb: string | null;
  createdAt: string;
  modifiedAt: string;
}

export interface TemplatesResponse {
  diviActive: boolean;
  templates: DiviTemplate[];
  count?: number;
  message?: string;
}

// ── Navigation ──────────────────────────────────────────────
export interface NavMenu {
  id: number;
  name: string;
  slug: string;
  count: number;
  locations: string[];
}

export interface NavMenuItem {
  id: number;
  title: string;
  url: string;
  type: string;
  object: string;
  objectId: number;
  parent: number;
  order: number;
  target: string;
  classes: string;
}

export interface MenuDetail {
  id: number;
  name: string;
  items: NavMenuItem[];
}

export interface MenuSources {
  pages: { id: number; title: string; url: string }[];
  posts: { id: number; title: string; url: string }[];
  categories: { id: number; title: string; url: string }[];
}

// ── Backups ─────────────────────────────────────────────────
export interface BackupItem {
  filename: string;
  sizeMb: number;
  createdAt: string;
}

export interface BackupsResponse {
  items: BackupItem[];
  totalSizeMb: number;
  dir?: string;
}

// ── Security ────────────────────────────────────────────────
export interface LoginAttempt {
  username: string;
  ip: string;
  userAgent: string;
  timestamp: string;
}

export interface SecurityCheck {
  id: string;
  label: string;
  ok: boolean;
  level: 'critical' | 'warning' | 'info';
}

export interface SecurityStatus {
  score: number;
  maxScore: number;
  percent: number;
  checks: SecurityCheck[];
}
