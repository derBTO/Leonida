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
  await expect(page.getByRole('dialog').locator('header h2')).toHaveText('E2E Admin-Versteck')
  await expect(page.getByRole('dialog').locator('.actions')).toHaveCount(0)

  // Dialog lässt sich am Kopf verschieben, Doppelklick setzt zurück
  const head = page.getByRole('dialog').locator('header')
  const before = (await page.getByRole('dialog').boundingBox())!
  const h = (await head.boundingBox())!
  await page.mouse.move(h.x + 60, h.y + h.height / 2)
  await page.mouse.down()
  await page.mouse.move(h.x + 260, h.y + h.height / 2 + 80, { steps: 5 })
  await page.mouse.up()
  const after = (await page.getByRole('dialog').boundingBox())!
  expect(Math.round(after.x - before.x)).toBe(200)
  expect(Math.round(after.y - before.y)).toBe(80)
  await head.dblclick({ position: { x: 60, y: h.height / 2 } })
  expect(Math.round((await page.getByRole('dialog').boundingBox())!.x)).toBe(Math.round(before.x))
  await page.keyboard.press('Escape')

  await page.goto('/waffen')
  // legendäre Waffe: Name in Gelb mit Stern
  await expect(page.locator('h3.legendary-name', { hasText: 'E2E Test-Pistole' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Neue Waffe' })).toHaveCount(0)
})

test('Bearbeiter legt einen Hinweis an und darf ihn bearbeiten', async ({ page }) => {
  await login(page, 'e2e-alice')
  await contextMenu(page, 'Neuer Hinweis hier')
  await page.locator('#pe-title').fill('E2E Zettel')
  await page.getByRole('button', { name: 'Code hinzufügen' }).click()
  await page.getByPlaceholder('Code *').fill('4711')
  await page.locator('button[type=submit][form=pin-form]').click()
  await expect(page.getByRole('dialog').locator('header h2')).toHaveText('E2E Zettel')
  await expect(page.getByRole('dialog')).toContainText('Hinweis')
  await expect(page.getByRole('dialog').locator('.actions')).toBeVisible()

  // Beim Bearbeiten lässt sich die Art eines Hinweises nicht mehr ändern
  await page.getByRole('dialog').getByRole('button', { name: 'Bearbeiten' }).click()
  await expect(page.locator('#pe-title')).toHaveValue('E2E Zettel')
  await expect(page.getByRole('dialog').getByRole('radiogroup', { name: 'Art', exact: true })).toHaveCount(0)

  // Ort direkt aus dem Hinweis anlegen: Karte, Fadenkreuz, zurück in den Hinweis
  await page.locator('#pe-place').fill('E2E Diner')
  await page.locator('.results').getByRole('button', { name: '„E2E Diner“ als neuen Ort auf der Karte anlegen' }).click()
  await expect(page.locator('.bar')).toContainText('Neuen Ort „E2E Diner“ für den Hinweis')
  await page.locator('.bar').getByRole('button', { name: 'Hier setzen' }).click()
  await expect(page.getByRole('dialog').locator('header h2')).toHaveText('Neuer Ort')
  await expect(page.getByRole('dialog').getByRole('radiogroup', { name: 'Art', exact: true })).toHaveCount(0)
  await expect(page.locator('#pe-title')).toHaveValue('E2E Diner')
  await page.locator('.poi-opt').first().click()
  await page.locator('button[type=submit][form=pin-form]').click()
  await expect(page.getByRole('dialog').locator('header h2')).toHaveText('Hinweis bearbeiten')
  await expect(page.locator('#pe-title')).toHaveValue('E2E Zettel')
  await expect(page.getByPlaceholder('Code *')).toHaveValue('4711')
  await expect(page.locator('.picker .chosen')).toContainText('E2E Diner')
  await page.locator('button[type=submit][form=pin-form]').click()
  await expect(page.getByRole('dialog').locator('.hint-head .links')).toContainText('E2E Diner')

  await page.keyboard.press('Escape')
  await page.getByRole('radio', { name: 'Hinweise', exact: true }).click()
  await expect(hits(page)).toHaveText('1')
  await page.getByRole('radio', { name: 'Alles', exact: true }).click()
})

test('Easteregg: eigenes Symbol unabhängig von der Art, Filter, Detail als Dialog', async ({ page }) => {
  await login(page, 'e2e-alice')
  await contextMenu(page, 'Neuer Loot-Pin hier')
  await page.locator('#pe-title').fill('E2E Geisterauto')
  await page.getByRole('checkbox', { name: /Easteregg/ }).check()
  await page.locator('button[type=submit][form=pin-form]').click()
  const dialog = page.getByRole('dialog')
  await expect(dialog.locator('header h2')).toHaveText('E2E Geisterauto')
  await expect(dialog.locator('.type.egg')).toHaveText('Easteregg')
  await page.keyboard.press('Escape')
  await expect(page).toHaveURL(/\/$/)
  await expect(page.locator('.egg-marker')).toHaveCount(1)
  await page.locator('.sidebar .select-row select').selectOption('only')
  await expect(hits(page)).toHaveText('1')
  await page.locator('.sidebar .select-row select').selectOption('hide')
  await expect(page.locator('.sidebar .row', { hasText: 'E2E Geisterauto' })).toHaveCount(0)
  await page.locator('.sidebar .select-row select').selectOption('show')
})

test('Puzzle anlegen mit Belohnung, Hinweis direkt aus dem Editor setzen, Fortschritt abhaken', async ({ page }) => {
  await login(page, 'e2e-alice')
  await page.goto('/puzzle')
  await page.getByRole('button', { name: 'Neues Puzzle' }).click()
  await page.locator('#tf-name').fill('E2E Puzzle')
  await page.getByPlaceholder('Waffe suchen …').fill('test-pistole')
  await page.locator('.menu li', { hasText: 'E2E Test-Pistole' }).click()
  const steps = page.locator('#trail-form li.step')
  await steps.nth(0).locator('input[required]').fill('Zettel lesen')
  await steps.nth(0).locator('input[type=search]').fill('zettel')
  await page.locator('.results .res', { hasText: 'E2E Zettel' }).click()
  await steps.nth(1).locator('input[required]').fill('Versteck finden')
  await steps.nth(1).getByRole('button', { name: 'Hinweis auf Karte setzen' }).click()

  // Fadenkreuz → Hinweis-Dialog (Art fest) mit Titel aus dem Schritt → zurück in den Editor
  await page.getByRole('button', { name: 'Hier setzen' }).click()
  await expect(page.locator('#pe-title')).toHaveValue('Versteck finden')
  await expect(page.getByRole('radio', { name: 'Loot-Fundort' })).toHaveCount(0)
  await page.locator('button[type=submit][form=pin-form]').click()
  await expect(page).toHaveURL(/\/puzzle$/)
  await expect(steps.nth(1).locator('.chosen')).toContainText('Versteck finden')
  await page.locator('button[type=submit][form=trail-form]').click()

  await expect(page).toHaveURL(/\/puzzle\/\d+$/)
  await expect(page.locator('.rewards')).toContainText('E2E Test-Pistole')
  await expect(page.locator('.step')).toHaveCount(2)
  await page.locator('.step .num').first().click()
  await expect(page.locator('.progress')).toContainText('1 von 2 erledigt')

  await page.getByRole('button', { name: 'Auf Karte zeigen' }).click()
  await expect(page.locator('.trail-bar')).toContainText('E2E Puzzle')
  await expect(page.locator('.trail-badge')).toHaveCount(2)
})

test('Hinweis mit Ort, Waffe und Schritten; Ort wird Fundort der Waffe', async ({ page }) => {
  await login(page, 'e2e-alice')
  await contextMenu(page, 'Neuer Hinweis hier')
  await page.locator('#pe-title').fill('E2E Inselhinweis')
  await page.locator('#pe-place').fill('tankstelle')
  await page.locator('.results .res', { hasText: 'E2E gtadb-Tankstelle' }).click()
  await page.getByPlaceholder('Waffe suchen …').fill('test-pistole')
  await page.locator('.menu li', { hasText: 'E2E Test-Pistole' }).click()
  await page.getByRole('button', { name: 'Schritt hinzufügen' }).click()
  await page.getByLabel('Schritt 1: Text').fill('Als Erstes müssen wir auf die Insel schwimmen.')
  await page.getByRole('button', { name: 'Schritt hinzufügen' }).click()
  // 1×1-PNG als Bild zu Schritt 2
  const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64')
  await page.locator('li.step').nth(1).locator('input[type=file]').setInputFiles({ name: 'insel.png', mimeType: 'image/png', buffer: png })
  await expect(page.locator('li.step').nth(1).locator('img')).toBeVisible()
  await page.getByLabel('Schritt 2: Text').fill('Hinter dem Haus liegt der Schlüssel.')
  await page.locator('button[type=submit][form=pin-form]').click()

  const dialog = page.getByRole('dialog')
  await expect(dialog.locator('header h2')).toHaveText('E2E Inselhinweis')
  await expect(dialog.locator('.hint-head .links')).toContainText('E2E gtadb-Tankstelle')
  await expect(dialog.locator('.hstep')).toHaveCount(2)
  await expect(dialog.locator('.hstep').nth(0)).toContainText('Als Erstes müssen wir auf die Insel schwimmen.')
  await expect(dialog.locator('.hstep').nth(1).locator('img')).toBeVisible()
  // nur die Schritte scrollen, der Kopf bleibt stehen
  expect(await dialog.locator('.hint-steps').evaluate((el) => getComputedStyle(el).overflowY)).toBe('auto')

  await dialog.locator('.link-chip', { hasText: 'E2E Test-Pistole' }).click()
  await expect(page.locator('.locations')).toContainText('E2E gtadb-Tankstelle')
  await expect(page.locator('.locations')).toContainText('über Hinweis')
  await expect(page.locator('.locations')).toContainText('E2E Puzzle')
})

test('Fortschritt ist pro Person, fremdes Puzzle nicht bearbeitbar', async ({ page }) => {
  await login(page, 'e2e-bob')
  await page.goto('/spuren')
  await expect(page).toHaveURL(/\/puzzle$/)
  await page.getByText('E2E Puzzle').click()
  await expect(page.locator('.progress')).toContainText('0 von 2 erledigt')
  await expect(page.getByRole('button', { name: 'Bearbeiten' })).toHaveCount(0)
})

test('Admin: Ersteller-Filter und Benutzer anlegen über das Menü', async ({ page }) => {
  await login(page, 'e2e-admin')
  await page.locator('.sidebar .author select').selectOption({ label: 'e2e-alice' })
  await expect(hits(page)).toHaveText('5')
  await page.getByText('Orte von gtadb.org zeigen').click()
  await expect(hits(page)).toHaveText('6')
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
  await expect(page.getByRole('dialog').locator('.actions')).toBeVisible()
})
