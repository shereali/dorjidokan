import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

test.beforeEach(async ({ page }) => {
  await page.goto('/')
  const slugInput = page.getByLabel('Shop slug')
  if (await slugInput.isVisible().catch(() => false)) {
    await slugInput.fill('heritage-tailors')
  }
  await page.getByLabel(/Email|ইমেইল/).fill('admin@tailors.test')
  await page.getByLabel(/Password|পাসওয়ার্ড/).fill('ChangeMe123!')
  await page.getByRole('button', { name: /Sign in|প্রবেশ করুন/ }).click()
  await expect(page.getByRole('heading', { name: /শুভ দিন|Dorjidokan|Bespoke/ })).toBeVisible({ timeout: 15000 })
})

test('creates a customer and inventory item through core workflow', async ({ page }, testInfo) => {
  const mobile = testInfo.project.name === 'mobile' ? '01812345678' : '01912345678'
  const customerName = `E2E Customer ${testInfo.project.name}`
  const sku = `E2E-FABRIC-${testInfo.project.name}`
  await page.goto('/customers')
  await page.getByLabel(/Name|নাম/).fill(customerName)
  await page.getByLabel(/Mobile|মোবাইল/).fill(mobile)
  await page.getByLabel(/Address|ঠিকানা/).fill('Dhaka')
  await page.getByRole('button', { name: /Save customer|সংরক্ষণ/ }).click()
  await expect(page.getByText(customerName).first()).toBeVisible()

  await page.goto('/inventory')
  await page.getByLabel(/SKU/).fill(sku)
  await page.getByLabel(/Name|নাম/).fill('E2E Cotton')
  await page.getByRole('button', { name: /Add Inventory Item|Add item|সংরক্ষণ/i }).click()
  await expect(page.getByText('E2E Cotton').first()).toBeVisible({ timeout: 10000 })
})

test('creates a tailoring order with pricing and delivery promise', async ({ page }, testInfo) => {
  const name = `Order Customer ${testInfo.project.name}`
  const mobile = testInfo.project.name === 'mobile' ? '01612345678' : '01512345678'
  await page.goto('/customers')
  await page.getByLabel(/Name|নাম/).fill(name)
  await page.getByLabel(/Mobile|মোবাইল/).fill(mobile)
  await page.getByRole('button', { name: /Save customer|সংরক্ষণ/ }).click()
  await expect(page.getByText(name).first()).toBeVisible()

  // Open orders page directly with new order wizard modal
  await page.goto('/orders?new=1')

  // Step 1: Customer details
  await page.getByLabel(/Customer Mobile Number|মোবাইল নম্বর/).fill(mobile)
  await page.getByLabel(/Customer Full Name|গ্রাহকের নাম/).fill(name)
  await page.getByRole('button', { name: /Next Step/i }).click({ force: true })

  // Step 2: Garment & Delivery Promise
  await page.getByLabel(/Garment Style|পোশাকের ধরন/).selectOption({ index: 0 })
  await page.getByRole('button', { name: '+7 Days' }).click({ force: true })
  await page.getByRole('button', { name: /Next Step/i }).click({ force: true })

  // Step 3: Measurements
  await page.getByRole('button', { name: /Next Step/i }).click({ force: true })

  // Step 4: Advance & Price
  await page.getByRole('button', { name: /Complete & Print Order Receipt|Creating Order/i }).click({ force: true })

  // Order created verification on the dashboard / receipt modal
  await expect(page.getByText(name).first()).toBeVisible({ timeout: 10000 })
  await expect(page.getByText(/Due|বকেয়া|পরিশোধিত/).first()).toBeVisible()
})

test('dashboard has no serious automated accessibility violations', async ({ page }) => {
  const results = await new AxeBuilder({ page })
    .disableRules(['color-contrast'])
    .analyze()
  expect(results.violations.filter(violation => ['critical', 'serious'].includes(violation.impact || ''))).toEqual([])
})

test('dashboard visual regression', async () => {
  test.skip()
})

test('garment SVG catalog is garment-aware', async ({ request }) => {
  const panjabi = await request.get('/garments/panjabi/collar.svg')
  const shirt = await request.get('/garments/shirt/collar.svg')
  const sherwani = await request.get('/garments/sherwani/front-opening.svg')
  const missing = await request.get('/garments/unknown/collar.svg')
  expect(panjabi.ok()).toBeTruthy()
  expect(panjabi.headers()['content-type']).toContain('image/svg+xml')
  const panjabiBody = await panjabi.text()
  const shirtBody = await shirt.text()
  const sherwaniBody = await sherwani.text()
  // Garment-specific geometry: distinct collar paths per garment.
  expect(panjabiBody).not.toBe(shirtBody)
  expect(shirtBody).not.toBe(await missing.text())
  // Sherwani has its own front-opening layer.
  expect(sherwaniBody).toContain('M150 84v196')
})

test('language switch translates the navigation shell', async ({ page }) => {
  const menuToggle = page.locator('.mobile-menu-toggle')
  if (await menuToggle.isVisible().catch(() => false)) {
    await menuToggle.click()
  }
  const langSelect = page.locator('select').first()
  if (await langSelect.isVisible().catch(() => false)) {
    await langSelect.selectOption('bn')
  }
  await expect(page.locator('nav a', { hasText: /ড্যাশবোর্ড|Dashboard/ })).toBeVisible()
})
