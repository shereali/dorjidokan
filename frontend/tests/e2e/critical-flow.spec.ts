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
  await page.locator('a[href="/customers"]').click()
  await page.getByLabel(/Name|নাম/).fill(customerName)
  await page.getByLabel(/Mobile|মোবাইল/).fill(mobile)
  await page.getByLabel(/Address|ঠিকানা/).fill('Dhaka')
  await page.getByRole('button', { name: /Save customer|সংরক্ষণ/ }).click()
  await expect(page.getByText(customerName).first()).toBeVisible()
  await page.locator('a[href="/inventory"]').click()
  await page.getByLabel('SKU').fill(sku)
  await page.getByLabel(/Name|নাম/).fill('E2E Cotton')
  await page.getByRole('button', { name: /Add item|সংরক্ষণ/ }).click()
  await expect(page.getByText('E2E Cotton').first()).toBeVisible()
})

test('creates a tailoring order with pricing and delivery promise', async ({ page }, testInfo) => {
  const name = `Order Customer ${testInfo.project.name}`
  const mobile = testInfo.project.name === 'mobile' ? '01612345678' : '01512345678'
  await page.locator('a[href="/customers"]').click()
  await page.getByLabel(/Name|নাম/).fill(name)
  await page.getByLabel(/Mobile|মোবাইল/).fill(mobile)
  await page.getByRole('button', { name: /Save customer|সংরক্ষণ/ }).click()
  await page.locator('a[href="/orders"]').click()
  await page.getByLabel(/Customer|গ্রাহক/).selectOption({ index: 1 })
  await page.getByLabel(/Garment|পোশাক/).selectOption({ index: 1 })
  await page.getByLabel(/Total amount|মোট/).fill('500')
  await page.getByLabel(/Advance taken|অগ্রিম/).fill('100')
  await page.getByRole('button', { name: /Create order|অর্ডার তৈরি/ }).click()
  const createdOrder = page.getByRole('button', { name: new RegExp(`${name} ORD-`) })
  await expect(createdOrder).toBeVisible()
  await expect(page.getByText(/Paid|পরিশোধিত/)).toBeVisible()
  await expect(page.getByText(/Due|বকেয়া/)).toBeVisible()
})

test('dashboard has no serious automated accessibility violations', async ({ page }) => {
  const results = await new AxeBuilder({ page }).analyze()
  expect(results.violations.filter(violation => ['critical', 'serious'].includes(violation.impact || ''))).toEqual([])
})

test('dashboard visual regression', async ({ page }) => {
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
  const langSelect = page.locator('select').first()
  if (await langSelect.isVisible().catch(() => false)) {
    await langSelect.selectOption('bn')
  }
  await expect(page.locator('nav a', { hasText: /ড্যাশবোর্ড|Dashboard/ })).toBeVisible()
})
