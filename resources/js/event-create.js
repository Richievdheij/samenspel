/** Form enhancements that leave the server-rendered form usable without JavaScript. */

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
