<style>
.inv-kitchen-page {
    --kit-gold: #c9a227;
    --kit-gold-light: #f5ecd7;
    --kit-cream: #fdfbf7;
    --kit-charcoal: #2c2416;
    --kit-warm: #8b6914;
    --kit-danger: #b91c1c;
    --kit-success: #15803d;
    --kit-radius: 14px;
    --kit-shadow: 0 4px 24px rgba(44,36,22,0.06);
}
.inv-kitchen-hero {
    background: linear-gradient(135deg, #1a1510 0%, #3d3220 45%, #5c4a2a 100%);
    border-radius: var(--kit-radius);
    padding: 28px 32px;
    color: #fff;
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
}
.inv-kitchen-hero::after {
    content: '';
    position: absolute;
    top: -40%;
    right: -10%;
    width: 280px;
    height: 280px;
    background: radial-gradient(circle, rgba(201,162,39,0.25) 0%, transparent 70%);
    pointer-events: none;
}
.inv-kitchen-hero h1 {
    font-size: 1.75rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    margin: 0 0 8px;
    color: #fff;
}
.inv-kitchen-hero p {
    margin: 0;
    opacity: 0.85;
    font-size: 0.95rem;
}
.inv-kitchen-hero__icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    background: rgba(201,162,39,0.2);
    border: 1px solid rgba(201,162,39,0.4);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    color: var(--kit-gold);
    margin-bottom: 16px;
}
.inv-kit-nav-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 24px;
    padding: 6px;
    background: #fff;
    border: 1px solid rgba(201,162,39,0.12);
    border-radius: 12px;
    box-shadow: var(--kit-shadow);
}
.inv-kit-nav-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    color: #57534e;
    text-decoration: none !important;
    transition: all 0.2s ease;
    flex: 1;
    justify-content: center;
    min-width: 140px;
}
.inv-kit-nav-tab .ti { font-size: 1rem; color: var(--kit-warm); }
.inv-kit-nav-tab:hover {
    background: var(--kit-gold-light);
    color: var(--kit-charcoal);
}
.inv-kit-nav-tab.is-active {
    background: linear-gradient(135deg, #3d3220, #5c4a2a);
    color: #fff;
    box-shadow: 0 2px 8px rgba(44,36,22,0.15);
}
.inv-kit-nav-tab.is-active .ti { color: var(--kit-gold); }
.inv-kit-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
}
.inv-kit-card {
    background: var(--kit-cream);
    border: 1px solid rgba(201,162,39,0.15);
    border-radius: var(--kit-radius);
    box-shadow: var(--kit-shadow);
    margin-bottom: 20px;
    overflow: hidden;
}
.inv-kit-card__head {
    padding: 18px 22px;
    border-bottom: 1px solid rgba(201,162,39,0.12);
    display: flex;
    align-items: center;
    gap: 14px;
}
.inv-kit-card__head h3 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--kit-charcoal);
}
.inv-kit-card__body { padding: 22px; }
.inv-kit-stat {
    background: #fff;
    border: 1px solid rgba(201,162,39,0.12);
    border-radius: 12px;
    padding: 20px 22px;
    display: flex;
    align-items: center;
    gap: 16px;
    height: 100%;
    transition: transform 0.2s, box-shadow 0.2s;
}
.inv-kit-stat:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(44,36,22,0.08);
}
.inv-kit-stat__icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.inv-kit-stat__icon--gold { background: var(--kit-gold-light); color: var(--kit-warm); }
.inv-kit-stat__icon--blue { background: #dbeafe; color: #1d4ed8; }
.inv-kit-stat__icon--green { background: #dcfce7; color: var(--kit-success); }
.inv-kit-stat__icon--red { background: #fef2f2; color: var(--kit-danger); }
.inv-kit-stat__content { flex: 1; min-width: 0; }
.inv-kit-stat__value {
    font-size: 1.65rem;
    font-weight: 700;
    color: var(--kit-warm);
    line-height: 1.2;
}
.inv-kit-stat__value--danger { color: var(--kit-danger); }
.inv-kit-stat__label {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #78716c;
    margin-top: 4px;
}
.inv-kit-steps {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 20px;
    position: relative;
}
.inv-kit-steps::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 5%;
    right: 5%;
    height: 2px;
    background: #e7e5e4;
    z-index: 0;
    display: none;
}
@media (min-width: 768px) {
    .inv-kit-steps::before { display: block; }
}
.inv-kit-step {
    flex: 1;
    min-width: 90px;
    text-align: center;
    padding: 12px 8px;
    border-radius: 10px;
    background: #fff;
    border: 2px solid #e7e5e4;
    font-size: 0.78rem;
    font-weight: 600;
    color: #78716c;
    transition: all 0.2s;
    position: relative;
    z-index: 1;
}
.inv-kit-step.is-active {
    border-color: var(--kit-gold);
    background: var(--kit-gold-light);
    color: var(--kit-charcoal);
    box-shadow: 0 2px 8px rgba(201,162,39,0.2);
}
.inv-kit-step.is-done {
    border-color: #a8a29e;
    background: #fafaf9;
    color: var(--kit-success);
}
.inv-kit-wizard-panel { display: none; animation: kitFadeIn 0.25s ease; }
.inv-kit-wizard-panel.is-visible { display: block; }
@keyframes kitFadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}
.inv-kit-mode-btn {
    border: 2px solid #e7e5e4;
    border-radius: 12px;
    padding: 20px 16px;
    text-align: center;
    cursor: pointer;
    background: #fff;
    transition: all 0.2s;
    height: 100%;
}
.inv-kit-mode-btn:hover, .inv-kit-mode-btn.is-selected {
    border-color: var(--kit-gold);
    background: var(--kit-gold-light);
    box-shadow: 0 2px 12px rgba(201,162,39,0.15);
}
.inv-kit-mode-btn .ti { font-size: 1.5rem; color: var(--kit-warm); display: block; margin-bottom: 8px; }
.inv-kit-mode-btn p {
    margin: 8px 0 0;
    font-size: 0.78rem;
    color: #78716c;
    font-weight: 400;
}
.inv-kit-nav { display: flex; justify-content: space-between; gap: 12px; margin-top: 24px; flex-wrap: wrap; }
.inv-kit-table { width: 100%; }
.inv-kit-table th {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #78716c;
    background: rgba(201,162,39,0.04);
    border-bottom: 1px solid rgba(201,162,39,0.12) !important;
    white-space: nowrap;
}
.inv-kit-table td { vertical-align: middle !important; }
.inv-kit-table tbody tr:hover { background: rgba(201,162,39,0.03); }
.inv-kit-badge-meal {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    background: var(--kit-gold-light);
    color: var(--kit-warm);
}
.inv-kit-low-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    background: #fef2f2;
    color: var(--kit-danger);
}
.inv-kit-empty {
    text-align: center;
    padding: 48px 24px;
    color: #78716c;
}
.inv-kit-empty .ti {
    font-size: 2.5rem;
    color: var(--kit-gold);
    opacity: 0.5;
    display: block;
    margin-bottom: 12px;
}
.inv-kit-alert {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 0.875rem;
    margin-bottom: 16px;
    display: none;
}
.inv-kit-alert.is-visible { display: block; }
.inv-kit-alert--error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: var(--kit-danger);
}
.inv-kit-alert--info {
    background: var(--kit-gold-light);
    border: 1px solid rgba(201,162,39,0.3);
    color: var(--kit-charcoal);
}
.inv-kit-meal-bar {
    margin-bottom: 14px;
}
.inv-kit-meal-bar__label {
    display: flex;
    justify-content: space-between;
    font-size: 0.875rem;
    margin-bottom: 6px;
}
.inv-kit-meal-bar__track {
    height: 8px;
    background: #f5f5f4;
    border-radius: 4px;
    overflow: hidden;
}
.inv-kit-meal-bar__fill {
    height: 100%;
    background: linear-gradient(90deg, var(--kit-warm), var(--kit-gold));
    border-radius: 4px;
    transition: width 0.4s ease;
}
.inv-kit-search {
    position: relative;
    max-width: 320px;
}
.inv-kit-search input {
    padding-left: 36px;
    border-radius: 10px;
    border: 1px solid rgba(201,162,39,0.2);
}
.inv-kit-search .ti {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #a8a29e;
}
.inv-kit-stock-warn { color: var(--kit-danger) !important; font-weight: 600; }
.inv-kit-stock-ok { color: var(--kit-success); }
@media (max-width: 767px) {
    .inv-kitchen-hero { padding: 20px; }
    .inv-kitchen-hero h1 { font-size: 1.35rem; }
    .inv-kit-nav-tab { min-width: 100%; }
    .inv-kit-step { font-size: 0.7rem; padding: 10px 4px; }
}
</style>
