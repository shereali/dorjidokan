import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

test.beforeEach(async ({ page }) => {
  await page.goto('/')
  await page.getByLabel('Shop slug').fill('heritage-tailors')
  await page.getByLabel('Email').fill('admin@tailors.test')
  await page.getByLabel('Password').fill('ChangeMe123!')
  await page.getByRole('button', { name: 'Sign in' }).click()
  await expect(page.getByRole('heading', { name: /শুভ সকাল/ })).toBeVisible()
})

test('creates a customer and inventory item through core workflow', async ({ page }, testInfo) => {
  const mobile = testInfo.project.name === 'mobile' ? '01812345678' : '01912345678'
  const customerName = `E2E Customer ${testInfo.project.name}`
  const sku = `E2E-FABRIC-${testInfo.project.name}`
  await page.getByRole('button', { name: /Customers/ }).click()
  await page.getByLabel('Name').fill(customerName)
  await page.getByLabel('Mobile').fill(mobile)
  await page.getByLabel('Address').fill('Dhaka')
  await page.getByRole('button', { name: 'Save customer' }).click()
  await expect(page.getByText(customerName).first()).toBeVisible()
  await page.getByRole('button', { name: /Inventory/ }).click()
  await page.getByLabel('SKU').fill(sku)
  await page.getByLabel('Name').fill('E2E Cotton')
  await page.getByRole('button', { name: 'Add item' }).click()
  await expect(page.getByText('E2E Cotton').first()).toBeVisible()
})

test('creates a tailoring order with pricing and delivery promise', async ({ page }, testInfo) => {
  const name = `Order Customer ${testInfo.project.name}`
  const mobile = testInfo.project.name === 'mobile' ? '01612345678' : '01512345678'
  await page.getByRole('button', { name: /Customers/ }).click()
  await page.getByLabel('Name').fill(name)
  await page.getByLabel('Mobile').fill(mobile)
  await page.getByRole('button', { name: 'Save customer' }).click()
  await page.getByRole('button', { name: /Orders/ }).click()
  await page.getByLabel('Customer').selectOption({ label: name })
  await page.getByLabel('Garment').selectOption({ label: 'Panjabi' })
  await page.getByLabel('Total amount (৳)').fill('500')
  await page.getByLabel('Advance taken (৳)').fill('100')
  await page.getByRole('button', { name: 'Create order' }).click()
  const createdOrder = page.getByRole('button', { name: new RegExp(`${name} ORD-`) })
  await expect(createdOrder).toBeVisible()
  await expect(page.getByText(/Paid ৳ 100\.00/)).toBeVisible()
  await expect(page.getByText(/Due ৳ 400\.00/)).toBeVisible()
})

test('dashboard has no serious automated accessibility violations', async ({ page }) => {
  const results = await new AxeBuilder({ page }).analyze()
  expect(results.violations.filter(violation => ['critical', 'serious'].includes(violation.impact || ''))).toEqual([])
})

test('dashboard visual regression', async ({ page }) => {
  await expect(page).toHaveScreenshot('dashboard.png', {
    fullPage: true,
    animations: 'disabled',
    mask: [page.locator('.metrics'), page.locator('.onboarding-panel'), page.locator('.grid .panel').first()],
  })
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
  await langSelect.selectOption('bn')
  await expect(page.getByRole('button', { name: /ড্যাশবোর্ড/ })).toBeVisible()
  await expect(page.getByRole('button', { name: /গ্রাহক/ })).toBeVisible()
  await langSelect.selectOption('en')
  await expect(page.getByRole('button', { name: /Dashboard/ })).toBeVisible()
})
