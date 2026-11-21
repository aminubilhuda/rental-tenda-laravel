# Design Specification --- Sales Dashboard

## 1. Overview

A desktop-first sales analytics dashboard for monitoring revenue, sales
targets, product contribution, salesperson performance, and lead
generation. The visual direction is a clean enterprise dashboard with a
strong red brand sidebar, white workspace, restrained borders, and
compact data visualizations.

**Reference:** `gambar(1).png`\
**Primary use case:** Sales managers and business owners reviewing
monthly performance at a glance.

## 2. Design Goals

-   Make the most important sales KPIs immediately scannable.
-   Present actual sales versus target with a clear visual comparison.
-   Show product mix, salesperson progress, and lead-channel performance
    in one screen.
-   Keep the interface professional, compact, and easy to extend.
-   Use color consistently: red for brand/targets, green for actuals and
    positive performance, amber for attention, and muted gray for
    supporting information.

## 3. Page Structure

The page is divided into two main regions:

1.  **Left navigation sidebar** --- fixed-width brand panel with primary
    navigation.
2.  **Main dashboard workspace** --- header, KPI cards, charts, and data
    tables arranged in a responsive grid.

### Desktop layout

-   Reference canvas: approximately 1556 × 959 px.
-   Sidebar: approximately 303 px wide, full viewport height.
-   Main content: remaining width, with roughly 40 px horizontal
    padding.
-   Header: title aligned left; search, notification, and profile
    controls aligned right.
-   KPI row: four equal-width cards.
-   Analytics row: large sales comparison chart (about two-thirds width)
    and product donut chart (about one-third width).
-   Bottom row: salesperson performance table and new leads table, each
    occupying approximately half the content width.

## 4. Visual Language

### Color palette

  Token             Suggested value   Usage
  ----------------- ----------------- -------------------------------------------
  Brand red         `#B51F32`         Sidebar background, brand identity
  Active red        `#941526`         Active navigation background
  Target red        `#B3263A`         Sales target bars
  Actual green      `#168A0B`         Actual sales bars and positive indicators
  Deep green        `#087A16`         Strong performance and lead response bars
  Soft green        `#D9F0D5`         Positive status backgrounds
  Amber             `#E8A900`         Warning / needs attention
  Soft amber        `#FFF0C2`         Warning status background
  Risk red          `#B84A5A`         At-risk values
  Soft risk         `#F7D9DE`         At-risk status background
  Text primary      `#111111`         Titles and key values
  Text secondary    `#777D7A`         Labels and descriptions
  Border            `#E7EBE8`         Card and input outlines
  Surface           `#FFFFFF`         Cards and content background
  Page background   `#FCFDFC`         Main workspace

### Typography

Use a modern sans-serif family such as **Inter**, **Manrope**, or **Plus
Jakarta Sans**.

  Element              Suggested style
  -------------------- ----------------------------
  Page title           34--36 px, weight 700
  Card KPI value       46--50 px, weight 700
  Section heading      21--23 px, weight 650--700
  Table heading        14--15 px, weight 650
  Body / table text    13--15 px, weight 400--500
  Supporting label     14--16 px, weight 400
  Sidebar brand        25--27 px, weight 700
  Sidebar navigation   18--20 px, weight 600

Use tabular numerals for currency, percentages, and metrics so values
align consistently.

## 5. Navigation Sidebar

**Appearance** - Full-height red panel on the left. - Rounded
upper-right and lower-right corners, approximately 18--20 px. - White
logo and wordmark near the top with generous spacing. - Navigation items
arranged vertically. - Active item uses a darker red rounded
rectangle. - Settings is anchored near the bottom.

**Navigation items** 1. Dashboard --- grid icon; active state. 2.
Product Details --- shopping bag icon. 3. Customers Data --- users icon.
4. Archives --- folder icon. 5. E-mail --- message-circle icon. 6.
Settings --- settings icon; bottom aligned.

**Interaction** - Hover: subtly lighten the navigation item
background. - Active: use the darker red background and maintain white
icon/text. - Keep icon size around 22--24 px with consistent
alignment. - On smaller screens, collapse the sidebar into a drawer or
compact icon rail.

## 6. Header

-   Page title: **Sales Dashboard**.
-   Search field at the upper-right, approximately 305 × 42 px.
-   Search field includes a search icon and muted placeholder.
-   Notification bell icon beside the search field.
-   Circular user avatar and small dropdown chevron at the far right.

**Behavior** - Search should filter relevant dashboard records or
navigate to matching records. - Notification control opens a
notification panel. - Avatar opens account/profile actions.

## 7. KPI Summary Cards

Display four cards in one row.

### Card 1 --- Sales

-   Label: `Sales`
-   Value: `IDR 16,4B`
-   Description: `Current Sales This Month`

### Card 2 --- Sales Target

-   Label: `Sales Target`
-   Value: `IDR 15,9B`
-   Description: `103% Achieve Target`

### Card 3 --- Sales Last Month

-   Label: `Sales Last Month`
-   Value: `IDR 11,5B`
-   Description: `Below the current sales level`

### Card 4 --- Gross Profit

-   Label: `Gross Profit`
-   Value: `IDR 8,5B`
-   Description: `Cost of goods sold`

**Card styling** - White background. - Thin light-gray border. - Rounded
corners: 10--12 px. - Internal padding: 20--22 px. - Label in muted
gray. - KPI value large, bold, and black. - Description below the KPI in
muted gray. - Equal card heights and consistent spacing.

**Data note:** The reference displays the Gross Profit card with the
description "Cost of goods sold". In a production dashboard, verify
whether this description should instead explain gross profit or whether
the metric is intended to be COGS.

## 8. Analytics Row

### 8.1 Actual Sales vs Target

A grouped vertical bar chart comparing actual sales and sales targets
from January to June.

**Chart title:** `Actual Sales vs Target`

**Legend** - Actual --- green (`#168A0B`) - Sales Target --- red
(`#B3263A`)

**X-axis:** Jan, Feb, Mar, Apr, May, Jun\
**Y-axis:** IDR 0B, 5B, 10B, 15B, 20B

**Reference data (approximate visual readings)**

  Month     Actual   Sales Target
  ------- -------- --------------
  Jan          11B           9.5B
  Feb          12B          10.5B
  Mar        14.5B            11B
  Apr        16.5B          13.5B
  May        17.5B          14.5B
  Jun        19.5B            15B

These values are approximate readings from the screenshot and should be
replaced with actual application data.

**Styling** - Green and red bars displayed side-by-side for each
month. - Light horizontal grid lines. - Minimal axis decoration. -
Compact legend in the chart header. - Period selector at the upper-right
(`This Month` in the reference). - Chart panel has a white background,
light border, and 10--12 px radius.

### 8.2 Sales by Product

A donut chart showing each product's share of sales.

**Chart title:** `Sales by Product`

**Reference data**

  Product         Share
  ------------- -------
  Rice Cooker     31.6%
  Oven            21.1%
  Stove           21.1%
  Frying Pan      10.5%
  Blender         10.5%

The listed percentages total 94.8%; the screenshot may omit or round a
remaining category. Use validated source data in the real dashboard.

**Styling** - Donut chart with a white center. - Percentage labels
placed inside or near each segment. - Product names arranged around the
chart. - Distinct but coordinated segment colors: red, orange, yellow,
and green shades. - Period selector at the upper-right. - Keep labels
legible and avoid overlap at narrower widths.

## 9. Bottom Data Panels

### 9.1 Sales Person Performance

A compact table with the following columns:

  Column        Description
  ------------- -------------------------------
  Salesperson   Avatar and salesperson name
  Sales         Achieved sales value
  Target        Sales target
  Progress      Percentage of target achieved
  Status        Performance label

**Reference rows**

  Salesperson             Sales     Target   Progress Status
  ------------------ ---------- ---------- ---------- -----------
  Michael Valent       IDR 350M   IDR 500M        70% Need Push
  Erica Christina      IDR 405M   IDR 450M       100% Exceeding
  Sandra Patricia      IDR 300M   IDR 400M        75% On Track
  Giovani Gonzales     IDR 207M   IDR 300M        69% At Risk

**Status treatment** - Need Push: amber pill with dark text. -
Exceeding: green pill with white text. - On Track: pale-green pill with
dark-green text. - At Risk: pale-red pill with dark-red text.

Sales values may use semantic emphasis: - Green for strong results. -
Amber for attention. - Red for at-risk performance.

Use small circular avatars, consistent row height, and clear column
alignment.

### 9.2 New Leads

A compact table with the following columns:

  Column             Description
  ------------------ -------------------------------
  Lead Source        Origin of the lead
  New Leads          Number of incoming leads
  Conversion         Conversion percentage
  Avg Contact Time   Average time to first contact

**Reference rows**

  Lead Source         New Leads   Conversion   Avg Contact Time
  ----------------- ----------- ------------ ------------------
  Website Form               82        12.2%            2.4 hrs
  LinkedIn Ads               55        15.0%            3.7 hrs
  WhatsApp                   38        18.9%            1.5 hrs
  Referral                   17        28.6%            0.9 hrs
  **Total Leads**       **192**    **16.5%**        **2.1 hrs**

**Styling** - New Leads values use pale-orange filled cells. -
Conversion values use pale-green filled cells. - Average contact time
uses dark-green filled cells with white text. - Use subtle corner
rounding on metric cells. - Total row uses bold text.

## 10. Components and Reuse

Recommended component structure:

``` text
SalesDashboard
├── Sidebar
│   ├── BrandLogo
│   ├── NavigationMenu
│   └── NavigationItem
├── DashboardHeader
│   ├── PageTitle
│   ├── SearchInput
│   ├── NotificationButton
│   └── UserMenu
├── KpiGrid
│   └── KpiCard × 4
├── AnalyticsGrid
│   ├── SalesComparisonCard
│   │   ├── ChartHeader
│   │   ├── ChartLegend
│   │   └── GroupedBarChart
│   └── ProductSalesCard
│       ├── ChartHeader
│       └── ProductDonutChart
└── PerformanceGrid
    ├── SalespersonPerformanceCard
    │   └── DataTable
    └── NewLeadsCard
        └── DataTable
```

Keep chart data, labels, and table rows separate from presentation
components. Reuse shared primitives for cards, headings, status pills,
period selectors, and table cells.

## 11. Spacing, Borders, and Shape

-   Main content horizontal padding: 40 px on the reference desktop
    layout.
-   Gap between KPI cards: 24--26 px.
-   Gap between dashboard rows: 24--26 px.
-   Card padding: 20--24 px.
-   Card radius: 10--12 px.
-   Input radius: 10--12 px.
-   Status pill radius: fully rounded.
-   Borders: 1 px solid `#E7EBE8`.
-   Avoid heavy shadows; use borders and whitespace to separate
    sections.

Use a consistent spacing scale based on 4 px: 4, 8, 12, 16, 20, 24, 32,
and 40 px.

## 12. Responsive Behavior

### Large desktop (≥ 1200 px)

-   Show the full sidebar.
-   KPI cards in four columns.
-   Analytics row in a two-column layout, with the bar chart wider than
    the donut chart.
-   Bottom panels side by side.

### Tablet (768--1199 px)

-   Collapse sidebar to an icon rail or drawer.
-   KPI cards in two columns.
-   Analytics panels may use a 1:1 split or stack if chart labels become
    crowded.
-   Bottom panels stack when table columns no longer fit.

### Mobile (\< 768 px)

-   Use a top navigation bar or slide-out menu.
-   KPI cards in one column or a compact two-column grid when space
    allows.
-   Stack all chart and table panels.
-   Allow tables to scroll horizontally rather than compressing text
    excessively.
-   Keep touch targets at least 44 × 44 px.
-   Reduce page title and KPI typography while preserving hierarchy.

## 13. Accessibility and Usability

-   Maintain readable contrast for all text and status indicators.
-   Do not communicate status using color alone; include text labels.
-   Provide accessible names for icon-only controls.
-   Ensure keyboard focus is visible.
-   Use semantic table headers and chart summaries.
-   Format currency and percentages consistently.
-   Provide loading, empty, and error states for cards, charts, and
    tables.
-   Include a clear date range or reporting period so users understand
    what the figures represent.

## 14. Functional Requirements

-   Period selectors update the KPI cards, charts, and tables to the
    selected reporting period.
-   KPI values are calculated from the same underlying dataset used by
    the charts and tables.
-   Sales target achievement is calculated as actual sales divided by
    target.
-   Product share is calculated from product sales divided by total
    product sales.
-   Salesperson progress is calculated as salesperson sales divided by
    individual target.
-   Lead conversion is calculated using a documented conversion
    definition.
-   Average contact time should specify whether it measures business
    hours or elapsed time.
-   Search, notifications, profile menu, and sidebar navigation should
    have functional interactions.

## 15. Implementation Notes

-   Treat the screenshot figures as illustrative design content, not
    authoritative business data.
-   Use a chart library suitable for the chosen frontend stack (for
    example, Chart.js, ApexCharts, or Recharts).
-   Prefer CSS Grid for the major dashboard regions and Flexbox for
    alignment within cards and headers.
-   Use CSS variables/design tokens for colors, radii, spacing, and
    typography.
-   Keep the layout fluid; avoid hard-coding the reference screenshot's
    pixel dimensions as fixed page widths.
-   Ensure number formatting supports Indonesian Rupiah (`IDR`) and
    localized separators where appropriate.

## 16. Design Summary

The intended result is a polished, information-dense sales dashboard
with a distinctive red navigation sidebar and a bright, neutral
workspace. Large KPI figures establish the overall sales position, the
paired charts explain sales and product performance, and the two bottom
tables provide operational detail about the sales team and incoming
leads.
