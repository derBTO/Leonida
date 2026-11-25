import { expect, test, type Page } from '@playwright/test'

/**
 * Durchgängige Browser-Tests mit den Testdaten aus `php artisan leonida:e2e-seed`:
 * e2e-admin (Administrator), e2e-alice und e2e-bob (Bearbeiter), ein gtadb-Ort,
 * ein Loot-Pin des Admins und eine Waffe. Die Tests bauen aufeinander auf (ein Worker, feste Reihenfolge).
 */
const PASSWORD = 'e2e-passwort-123'

async function login(page: Page, username: string) {
  await page.goto('/')
  await page.getByRole('button', { name: 'Anmelden' }).click()
  await page.locator('input[autocomplete=username]').fill(username)
  await page.locator('input[type=password]').fill(PASSWORD)
  await page.keyboard.press('Enter')
  await expect(page.locator('.user')).toContainText(username)
}

const hits = (page: Page) => page.locator('.sidebar .count strong')

/** Rechtsklick in die Kartenmitte und einen Menüpunkt wählen */
async function contextMenu(page: Page, item: string) {
  const box = (await page.locator('.map').boundingBox())!
  await page.mouse.click(box.x + box.width * 0.6, box.y + box.height * 0.5, { button: 'right' })
  await page.getByRole('button', { name: item }).click()
}

test.describe.configure({ mode: 'serial' })

test('Gäste sehen die Karte, gtadb-Orte nur mit Schalter, keinen Ersteller-Filter', async ({ page }) => {
  await page.goto('/')
  await expect(hits(page)).toHaveText('1')
  await expect(page.locator('.sidebar .author')).toHaveCount(0)
  await page.getByText('Orte von gtadb.org zeigen').click()
  await expect(hits(page)).toHaveText('2')
  await expect(page.locator('.sidebar .row', { hasText: 'E2E gtadb-Tankstelle' })).toBeVisible()
})

test('Bearbeiter dürfen Fremdes nicht ändern und keine Waffen anlegen', async ({ page }) => {
  await login(page, 'e2e-alice')
  await page.locator('.sidebar .row', { hasText: 'E2E Admin-Versteck' }).click()
  await expect(page.locator('.detail .title')).toHaveText('E2E Admin-Versteck')
  await expect(page.locator('.detail .actions')).toHaveCount(0)

  await page.goto('/waffen')
  await expect(page.getByText('E2E Test-Pistole')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Neue Waffe' })).toHaveCount(0)
})

test('Bearbeiter legt einen Hinweis an und darf ihn bearbeiten', async ({ page }) => {
  await login(page, 'e2e-alice')
  await contextMenu(page, 'Neuer Hinweis hier')
  await page.locator('#pe-title').fill('E2E Zettel')
  await page.getByRole('button', { name: 'Code hinzufügen' }).click()
  await page.getByPlaceholder('Code *').fill('4711')
  await page.locator('button[type=submit][form=pin-form]').click()
  await expect(page.locator('.detail .title')).toHaveText('E2E Zettel')
  await expect(page.locator('.detail')).toContainText('Hinweis')
  await expect(page.locator('.detail .actions')).toBeVisible()
  await page.getByRole('radio', { name: 'Hinweise', exact: true }).click()
  await expect(hits(page)).toHaveText('1')
})

test('Spur anlegen, Pin direkt aus dem Editor setzen, Fortschritt abhaken', async ({ page }) => {
  await login(page, 'e2e-alice')
  await page.goto('/spuren')
  await page.getByRole('button', { name: 'Neue Spur' }).click()
  await page.locator('#tf-name').fill('E2E Spur')
  const steps = page.locator('#trail-form li.step')
  await steps.nth(0).locator('input[required]').fill('Zettel lesen')
  await steps.nth(0).locator('input[type=search]').fill('zettel')
  await page.locator('.results .res', { hasText: 'E2E Zettel' }).click()
  await steps.nth(1).locator('input[required]').fill('Versteck finden')
  await steps.nth(1).getByRole('button', { name: 'Auf Karte setzen' }).click()

  // Fadenkreuz → Pin-Dialog mit Titel aus dem Schritt → zurück in den Editor
  await page.getByRole('button', { name: 'Hier setzen' }).click()
  await expect(page.locator('#pe-title')).toHaveValue('Versteck finden')
  await page.getByRole('radio', { name: 'Loot-Fundort' }).click()
  await page.locator('button[type=submit][form=pin-form]').click()
  await expect(page).toHaveURL(/\/spuren$/)
  await expect(steps.nth(1).locator('.chosen')).toContainText('Versteck finden')
  await page.locator('button[type=submit][form=trail-form]').click()

  await expect(page).toHaveURL(/\/spuren\/\d+$/)
  await expect(page.locator('.step')).toHaveCount(2)
  await page.locator('.step .num').first().click()
  await expect(page.locator('.progress')).toContainText('1 von 2 erledigt')

  await page.getByRole('button', { name: 'Auf Karte zeigen' }).click()
  await expect(page.locator('.trail-bar')).toContainText('E2E Spur')
  await expect(page.locator('.trail-badge')).toHaveCount(2)
})

test('Fortschritt ist pro Person, fremde Spur nicht bearbeitbar', async ({ page }) => {
  await login(page, 'e2e-bob')
  await page.goto('/spuren')
  await page.getByText('E2E Spur').click()
  await expect(page.locator('.progress')).toContainText('0 von 2 erledigt')
  await expect(page.getByRole('button', { name: 'Bearbeiten' })).toHaveCount(0)
})

test('Admin: Ersteller-Filter und Benutzer anlegen über das Menü', async ({ page }) => {
  await login(page, 'e2e-admin')
  await page.locator('.sidebar .author select').selectOption({ label: 'e2e-alice' })
  await expect(hits(page)).toHaveText('2')
  await page.getByText('Orte von gtadb.org zeigen').click()
  await expect(hits(page)).toHaveText('3')
  await page.locator('.sidebar .author select').selectOption({ label: 'Alle' })

  await page.locator('.user .btn').click()
  await page.getByRole('link', { name: 'Benutzer anlegen' }).click()
  await expect(page).toHaveURL(/\/admin/)
  const username = page.locator('section form input').first()
  await expect(username).toBeFocused()
  await username.fill('e2e-carol')
  await page.locator('input[autocomplete=off]').fill('start-passwort-123')
  await page.getByRole('button', { name: 'Anlegen' }).click()
  await expect(page.locator('table')).toContainText('e2e-carol')

  // Admin darf fremde Pins bearbeiten
  await page.goto('/')
  await page.locator('.sidebar .row', { hasText: 'E2E Zettel' }).click()
  await expect(page.locator('.detail .actions')).toBeVisible()
})
