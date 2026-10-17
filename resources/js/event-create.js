/** Form enhancements that leave the server-rendered form usable without JavaScript. */

/**
 * Search the game catalog from inside the games dropdown. The games already known
 * locally are listed and filtered like any other option; from three characters on,
 * catalog matches that are not yet local appear below them. Choosing one stores it
 * locally and turns it into an ordinary, checked option, so the list keeps the
 * choice in view and the dropdown stays open for the next one.
 */
export function initialiseGameCatalog() {
  const select = document.querySelector('[data-event-games]')
  if (!select) return

  const form = select.closest('form')
  const search = select.querySelector('[data-option-search]')
  const localResults = select.querySelector('[data-local-results]')
  const results = select.querySelector('[data-game-results]')
  const resultsHeading = select.querySelector('[data-game-results-heading]')
  const status = select.querySelector('[data-game-status]')
  const csrf = form?.querySelector('[name="_token"]')?.value
  let timer
  let pending

  const showResults = (buttons) => {
    results.replaceChildren(...buttons)
    resultsHeading.hidden = buttons.length === 0
  }

  const visibleLocalRows = () =>
    [...localResults.querySelectorAll('[data-option-row]')].some((row) => !row.hidden)

  const optionFor = (game) => {
    const label = document.createElement('label')
    label.className = 'event-create__option'
    label.dataset.optionRow = ''
    if (game.provider_id) label.dataset.providerId = game.provider_id
    const input = document.createElement('input')
    input.type = 'checkbox'
    input.className = 'checkbox__control'
    input.name = 'games[]'
    input.value = game.id
    const logo = document.createElement('span')
    logo.className = 'event-create__game-option-logo'
    logo.setAttribute('aria-hidden', 'true')
    if (game.cover_url) {
      const image = document.createElement('img')
      image.src = game.cover_url
      image.alt = ''
      logo.append(image)
    } else {
      logo.textContent = game.name.slice(0, 2).toLocaleUpperCase()
    }
    const name = document.createElement('span')
    name.dataset.optionName = ''
    name.textContent = game.name
    label.append(input, logo, name)
    localResults.append(label)
    return input
  }

  const choose = async (providerId) => {
    const response = await fetch(select.dataset.gameAddUrl, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
      },
      body: JSON.stringify({
        provider_id: providerId,
        ...(select.dataset.eventId ? { event_id: select.dataset.eventId } : {}),
      }),
    })
    const data = await response.json()
    if (!response.ok) throw new Error(data.message || select.dataset.selectError)

    const input =
      [...localResults.querySelectorAll('input[name="games[]"]')].find(
        (item) => item.value === String(data.game.id),
      ) ?? optionFor(data.game)
    input.closest('[data-option-row]').hidden = false
    input.checked = true
    input.dispatchEvent(new Event('change', { bubbles: true }))
    return input
  }

  const resultFor = (game) => {
    const button = document.createElement('button')
    button.type = 'button'
    button.className = 'event-create__catalog-result'
    button.dataset.providerId = game.id
    const cover = game.cover_url ? document.createElement('img') : document.createElement('span')
    if (game.cover_url) {
      cover.src = game.cover_url
      cover.alt = ''
      cover.loading = 'lazy'
    } else {
      cover.textContent = game.name.slice(0, 2).toLocaleUpperCase()
    }
    const information = document.createElement('span')
    information.className = 'event-create__catalog-info'
    const title = document.createElement('strong')
    title.className = 'event-create__catalog-title'
    title.textContent = game.name
    const metadata = document.createElement('small')
    metadata.className = 'event-create__catalog-meta'
    metadata.textContent = [
      ...(game.platforms ?? []).slice(0, 2),
      ...(game.platforms?.length > 2 ? [`+${game.platforms.length - 2}`] : []),
      ...(game.release_year ? [String(game.release_year)] : []),
    ].join(' · ')
    information.append(title, metadata)
    const add = document.createElement('span')
    add.className = 'event-create__catalog-add'
    add.setAttribute('aria-hidden', 'true')
    add.textContent = '+'
    button.append(cover, information, add)
    button.addEventListener('click', async () => {
      button.disabled = true
      try {
        const input = await choose(game.id)
        button.remove()
        resultsHeading.hidden = results.childElementCount === 0
        status.textContent = ''
        // The button that had focus is gone; keep the keyboard on the choice it made.
        input.focus()
      } catch (error) {
        status.textContent = error.message
        button.disabled = false
      }
    })
    return button
  }

  search.addEventListener('input', () => {
    clearTimeout(timer)
    pending?.abort()
    showResults([])
    const term = search.value.trim()
    status.textContent = select.dataset.searchHint
    if (term.length < 3 || term.length > 80) return
    if (!/^[\p{L}\p{N}][\p{L}\p{N} .:’'&+_-]*$/u.test(term)) {
      status.textContent = select.dataset.searchInvalid
      return
    }
    timer = setTimeout(async () => {
      pending = new AbortController()
      try {
        const url = new URL(select.dataset.catalogSearchUrl, window.location.href)
        url.searchParams.set('q', term)
        const response = await fetch(url, {
          signal: pending.signal,
          headers: { Accept: 'application/json' },
        })
        const data = await response.json()
        if (!response.ok) throw new Error(data.message || select.dataset.selectError)
        if (search.value.trim() !== term) return
        const localIds = new Set(
          [...localResults.querySelectorAll('[data-option-row][data-provider-id]')].map(
            (row) => row.dataset.providerId,
          ),
        )
        const buttons = data.results.filter((game) => !localIds.has(String(game.id))).map(resultFor)
        showResults(buttons)
        status.textContent =
          buttons.length === 0 && !visibleLocalRows() ? select.dataset.searchEmpty : ''
      } catch (error) {
        if (error.name !== 'AbortError') status.textContent = error.message
      }
    }, 350)
  })
}

export function initialiseLocationSearch(form) {
  const fieldset = form.querySelector('[data-event-location]')
  if (!fieldset) return

  const reference = fieldset.querySelector('[data-location-reference]')
  const queries = [...fieldset.querySelectorAll('[data-location-query]')]
  const addressParts = fieldset.querySelector('[data-address-parts]')
  const timers = new Map()
  const pending = new Map()

  const clearResults = () => {
    for (const query of queries) {
      const results = query
        .closest('[data-location-panel]')
        .querySelector('[data-location-results]')
      results.replaceChildren()
      results.hidden = true
      query.setAttribute('aria-expanded', 'false')
    }
  }

  const syncMode = () => {
    const mode = fieldset.querySelector('input[name="location_mode"]:checked')?.value
    for (const panel of fieldset.querySelectorAll('[data-location-panel]')) {
      const active = panel.dataset.locationPanel === mode
      for (const input of panel.querySelectorAll('input')) input.disabled = !active
    }
    addressParts.hidden = mode !== 'search' || reference.value === ''
    clearResults()
  }

  fieldset.addEventListener('change', (event) => {
    if (event.target.name === 'location_mode') syncMode()
  })
  syncMode()

  for (const query of queries) {
    const panel = query.closest('[data-location-panel]')
    const results = panel.querySelector('[data-location-results]')
    const status = panel.querySelector('[data-location-status]')

    query.addEventListener('input', () => {
      reference.value = ''
      addressParts.hidden = true
      status.textContent = fieldset.dataset.addressPrompt
      results.replaceChildren()
      results.hidden = true
      query.setAttribute('aria-expanded', 'false')
      clearTimeout(timers.get(query))
      pending.get(query)?.abort()
      const term = query.value.trim()
      if (term.length < 3) return

      timers.set(
        query,
        setTimeout(async () => {
          const controller = new AbortController()
          pending.set(query, controller)
          try {
            const url = new URL(fieldset.dataset.addressUrl, window.location.href)
            url.searchParams.set('q', term)
            const response = await fetch(url, {
              signal: controller.signal,
              headers: { Accept: 'application/json' },
            })
            if (!response.ok) throw new Error('Location search failed')
            const data = await response.json()
            if (query.value.trim() !== term) return
            if (data.results.length === 0) {
              status.textContent = fieldset.dataset.addressEmpty
              return
            }
            const options = data.results.map((location) => {
              const button = document.createElement('button')
              button.type = 'button'
              button.className = 'event-create__location-result'
              button.setAttribute('role', 'option')
              button.dataset.addressId = location.id
              button.dataset.street = location.street
              button.dataset.number = location.number
              button.dataset.postcode = location.postcode
              button.dataset.city = location.city
              button.textContent = location.label
              return button
            })
            results.replaceChildren(...options)
            results.hidden = false
            query.setAttribute('aria-expanded', 'true')
          } catch (error) {
            if (error.name !== 'AbortError')
              status.textContent = fieldset.dataset.addressUnavailable
          }
        }, 350),
      )
    })

    results.addEventListener('click', async (event) => {
      const option = event.target.closest('[data-address-id]')
      if (!option) return
      query.value = option.textContent
      status.textContent = fieldset.dataset.addressPrompt
      try {
        const url = new URL(fieldset.dataset.addressUrl, window.location.href)
        url.searchParams.set('id', option.dataset.addressId)
        const response = await fetch(url, { headers: { Accept: 'application/json' } })
        if (!response.ok) throw new Error('Location lookup failed')
        const { location } = await response.json()
        reference.value = location.id
        status.textContent = fieldset.dataset.addressSelected
        for (const part of addressParts.querySelectorAll('[data-address-part]')) {
          part.value = location[part.dataset.addressPart]
        }
        addressParts.hidden = false
      } catch {
        reference.value = ''
        status.textContent = fieldset.dataset.addressUnavailable
        addressParts.hidden = true
      }
      clearResults()
      query.focus()
    })

    query.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowDown' && !results.hidden) {
        event.preventDefault()
        results.querySelector('button')?.focus()
      }
      if (event.key === 'Escape') clearResults()
    })
    results.addEventListener('keydown', (event) => {
      if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp' && event.key !== 'Escape') return
      event.preventDefault()
      if (event.key === 'Escape') {
        clearResults()
        query.focus()
        return
      }
      const options = [...results.querySelectorAll('button')]
      const index = options.indexOf(event.target)
      options[index + (event.key === 'ArrowDown' ? 1 : -1)]?.focus()
    })
  }
  document.addEventListener('click', (event) => {
    if (!fieldset.contains(event.target)) clearResults()
  })
}

const pad = (number) => String(number).padStart(2, '0')
const addMinute = (value) => {
  const date = new Date(`${value}Z`)
  date.setUTCMinutes(date.getUTCMinutes() + 1)
  return date.toISOString().slice(0, 16)
}

export function initialiseSchedulePicker(form) {
  const enhanced = form.querySelector('[data-schedule-enhanced]')
  const fallback = form.querySelector('[data-schedule-fallback]')
  const startsAt = form.querySelector('[data-starts-at]')
  const endsAt = form.querySelector('[data-ends-at]')
  if (!enhanced || !fallback || !startsAt || !endsAt) return

  const details = enhanced.querySelector('[data-schedule-details]')
  const summary = enhanced.querySelector('[data-schedule-summary]')
  const calendars = [...enhanced.querySelectorAll('.event-create__schedule-calendar')]
  const hour = enhanced.querySelector('[data-schedule-hour]')
  const minute = enhanced.querySelector('[data-schedule-minute]')
  const locale = document.documentElement.lang || 'nl-NL'
  const shortDate = new Intl.DateTimeFormat(locale, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
  const longDate = new Intl.DateTimeFormat(locale, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
  const monthDate = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' })
  let mode = 'start'
  let visibleMonth = startsAt.value.slice(0, 7)
  let draft = { start: startsAt.value, end: endsAt.value }

  const asDate = (date) => new Date(`${date}T12:00:00`)
  const display = (value) =>
    `${shortDate.format(asDate(value.slice(0, 10)))} · ${value.slice(11, 16)}`
  const selected = () => draft[mode]
  const dateKey = (date) =>
    `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}`
  const shiftedDate = (date, days) => {
    const shifted = new Date(`${date}T12:00:00Z`)
    shifted.setUTCDate(shifted.getUTCDate() + days)
    return dateKey(shifted)
  }
  const shiftedMonth = (month, offset) => {
    const [year, number] = month.split('-').map(Number)
    return new Date(Date.UTC(year, number - 1 + offset, 1)).toISOString().slice(0, 7)
  }

  for (let value = 0; value < 24; value++) hour.add(new Option(pad(value), pad(value)))
  for (let value = 0; value < 60; value++) minute.add(new Option(pad(value), pad(value)))

  const syncSummary = () => {
    const start = startsAt.value ? display(startsAt.value) : ''
    const end = endsAt.value ? display(endsAt.value) : enhanced.dataset.addEndLabel
    summary.textContent = `${start}  →  ${end}`
  }

  const syncDraft = () => {
    enhanced.querySelector('[data-schedule-start-label]').textContent = display(draft.start)
    enhanced.querySelector('[data-schedule-end-label]').textContent = draft.end
      ? display(draft.end)
      : enhanced.dataset.addEndLabel
    enhanced.querySelector('[data-schedule-remove]').hidden = !draft.end
  }

  const syncTime = () => {
    const value = selected() || draft.start
    hour.value = value.slice(11, 13)
    minute.value = value.slice(14, 16)
  }

  const renderCalendar = (calendar, monthKey) => {
    const [year, month] = monthKey.split('-').map(Number)
    const first = new Date(Date.UTC(year, month - 1, 1))
    const offset = (first.getUTCDay() + 6) % 7
    const count = new Date(Date.UTC(year, month, 0)).getUTCDate()
    const slots = Math.max(35, Math.ceil((offset + count) / 7) * 7)
    const minimum = startsAt.min.slice(0, 10)
    calendar.querySelector('[data-calendar-month]').textContent = monthDate.format(
      asDate(`${monthKey}-01`),
    )

    const buttons = []
    for (let slot = 0; slot < slots; slot++) {
      const date = shiftedDate(`${monthKey}-01`, slot - offset)
      const button = document.createElement('button')
      button.type = 'button'
      button.textContent = String(Number(date.slice(8, 10)))
      button.dataset.calendarDate = date
      button.setAttribute('aria-label', longDate.format(asDate(date)))
      button.setAttribute(
        'aria-pressed',
        String(draft.start.startsWith(date) || draft.end.startsWith(date)),
      )
      button.disabled = date < minimum
      if (!date.startsWith(monthKey)) button.dataset.outsideMonth = ''
      if (date === startsAt.min.slice(0, 10)) button.dataset.today = ''
      if (date === draft.start.slice(0, 10)) button.dataset.rangeStart = ''
      if (draft.end && date === draft.end.slice(0, 10)) button.dataset.rangeEnd = ''
      if (draft.end && date > draft.start.slice(0, 10) && date < draft.end.slice(0, 10))
        button.dataset.inRange = ''
      buttons.push(button)
    }
    calendar.querySelector('[data-calendar-days]').replaceChildren(...buttons)
  }

  const render = () => {
    calendars.forEach((calendar, index) =>
      renderCalendar(calendar, shiftedMonth(visibleMonth, index)),
    )
    enhanced.querySelector('[data-calendar-previous]').disabled =
      visibleMonth <= startsAt.min.slice(0, 7)
  }

  const setMode = (value) => {
    mode = value
    for (const tab of enhanced.querySelectorAll('[data-schedule-tab]')) {
      tab.setAttribute('aria-pressed', String(tab.dataset.scheduleTab === value))
    }
    syncTime()
    render()
  }

  const clearPreset = () => {
    for (const preset of enhanced.querySelectorAll('[data-schedule-preset]')) {
      preset.setAttribute('aria-pressed', 'false')
    }
  }

  const chooseDate = (date) => {
    const candidate = `${date}T${hour.value}:${minute.value}`
    if (mode === 'start') {
      draft.start = candidate < startsAt.min ? startsAt.min : candidate
      draft.end = ''
      mode = 'end'
      for (const tab of enhanced.querySelectorAll('[data-schedule-tab]')) {
        tab.setAttribute('aria-pressed', String(tab.dataset.scheduleTab === mode))
      }
    } else if (date < draft.start.slice(0, 10)) {
      draft.start = candidate < startsAt.min ? startsAt.min : candidate
      draft.end = ''
    } else {
      draft.end = candidate <= draft.start ? addMinute(draft.start) : candidate
    }
    clearPreset()
    syncTime()
    syncDraft()
    render()
  }

  const updateTime = () => {
    const candidate = `${(selected() || draft.start).slice(0, 10)}T${hour.value}:${minute.value}`
    if (mode === 'start') {
      draft.start = candidate < startsAt.min ? startsAt.min : candidate
      if (draft.end && draft.end <= draft.start) draft.end = ''
    } else {
      draft.end = candidate <= draft.start ? addMinute(draft.start) : candidate
    }
    clearPreset()
    syncTime()
    syncDraft()
    render()
  }

  for (const calendar of calendars) {
    const weekdays = calendar.querySelector('[data-calendar-weekdays]')
    for (let index = 0; index < 7; index++) {
      const day = new Date(Date.UTC(2024, 0, 1 + index))
      const label = document.createElement('span')
      label.textContent = new Intl.DateTimeFormat(locale, { weekday: 'short' }).format(day)
      weekdays.append(label)
    }
  }

  enhanced.querySelector('[data-calendar-previous]').addEventListener('click', () => {
    visibleMonth = shiftedMonth(visibleMonth, -1)
    render()
  })
  for (const next of enhanced.querySelectorAll('[data-calendar-next]')) {
    next.addEventListener('click', () => {
      visibleMonth = shiftedMonth(visibleMonth, 1)
      render()
    })
  }
  for (const calendar of calendars) {
    calendar.querySelector('[data-calendar-days]').addEventListener('click', (event) => {
      const date = event.target.closest('[data-calendar-date]')?.dataset.calendarDate
      if (date) chooseDate(date)
    })
  }
  for (const tab of enhanced.querySelectorAll('[data-schedule-tab]')) {
    tab.addEventListener('click', () => setMode(tab.dataset.scheduleTab))
  }
  for (const select of [hour, minute]) select.addEventListener('change', updateTime)
  for (const preset of enhanced.querySelectorAll('[data-schedule-preset]')) {
    preset.setAttribute('aria-pressed', 'false')
    preset.addEventListener('click', () => {
      const earliest = startsAt.min.slice(0, 10)
      const weekday = (new Date(`${earliest}T12:00:00Z`).getUTCDay() + 6) % 7
      const weekStart = shiftedDate(earliest, 7 - weekday)
      const thisMonthEnd = shiftedDate(`${shiftedMonth(earliest.slice(0, 7), 1)}-01`, -1)
      const nextMonth = shiftedMonth(earliest.slice(0, 7), 1)
      const periods = {
        today: [earliest, earliest],
        tomorrow: [shiftedDate(earliest, 1), shiftedDate(earliest, 1)],
        week: [earliest, shiftedDate(earliest, 6 - weekday)],
        'next-week': [weekStart, shiftedDate(weekStart, 6)],
        month: [earliest, thisMonthEnd],
        'next-month': [`${nextMonth}-01`, shiftedDate(`${shiftedMonth(nextMonth, 1)}-01`, -1)],
      }
      const [first, last] = periods[preset.dataset.schedulePreset]
      draft.start = `${first}T${draft.start.slice(11, 16)}`
      if (draft.start < startsAt.min) draft.start = startsAt.min
      draft.end = `${last}T23:59`
      if (draft.end <= draft.start) draft.end = addMinute(draft.start)
      visibleMonth = first.slice(0, 7)
      mode = 'start'
      for (const tab of enhanced.querySelectorAll('[data-schedule-tab]')) {
        tab.setAttribute('aria-pressed', String(tab.dataset.scheduleTab === mode))
      }
      for (const option of enhanced.querySelectorAll('[data-schedule-preset]')) {
        option.setAttribute('aria-pressed', String(option === preset))
      }
      syncDraft()
      syncTime()
      render()
    })
  }
  enhanced.querySelector('[data-schedule-remove]').addEventListener('click', () => {
    draft.end = ''
    clearPreset()
    syncDraft()
    setMode('start')
  })
  const close = (apply, restoreFocus = true) => {
    if (apply) {
      startsAt.value = draft.start
      endsAt.value = draft.end
      syncSummary()
    }
    draft = { start: startsAt.value, end: endsAt.value }
    clearPreset()
    syncDraft()
    setMode('start')
    details.open = false
    if (restoreFocus) details.querySelector('summary')?.focus()
  }
  details.querySelector('summary')?.addEventListener('click', (event) => {
    event.preventDefault()
    if (details.open) {
      close(false)
    } else {
      draft = { start: startsAt.value, end: endsAt.value }
      visibleMonth = draft.start.slice(0, 7)
      clearPreset()
      syncDraft()
      setMode('start')
      details.open = true
    }
  })
  enhanced.querySelector('[data-schedule-cancel]').addEventListener('click', () => close(false))
  enhanced.querySelector('[data-schedule-apply]').addEventListener('click', () => close(true))
  details.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      close(false)
    }
  })
  document.addEventListener('click', (event) => {
    if (details.open && !event.composedPath().includes(details)) close(false, false)
  })

  startsAt.type = 'hidden'
  endsAt.type = 'hidden'
  fallback.hidden = true
  enhanced.hidden = false
  syncSummary()
  syncDraft()
  setMode('start')
}
