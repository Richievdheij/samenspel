import {
  initialiseGameCatalog,
  initialiseLocationSearch,
  initialiseSchedulePicker,
} from './event-create.js'

/**
 * The application entry point Vite bundles.
 *
 * Deliberately small. This template ships no frontend framework: Blade renders
 * the page and this file is where progressive enhancement goes. Keep it that way
 * until a real need arrives — every dependency added here is one every teammate
 * has to reason about.
 *
 * Every function below improves markup that already works without it: the menus
 * are <details>, the modal is a <dialog> opened by an invoker command.
 */

/**
 * Dismiss a flash message.
 *
 * Written out rather than left as a comment so there is one worked example of the
 * conventions: query by data attribute, never by a CSS class (a class is for
 * styling and gets renamed), and leave the markup usable without JavaScript.
 */
function initialiseDismissibleAlerts() {
  for (const button of document.querySelectorAll('[data-dismiss="alert"]')) {
    button.addEventListener('click', () => {
      button.closest('.alert')?.remove()
    })
  }
}

/** Show the search reset as soon as text is entered, before the form submits. */
function initialiseEventSearchReset() {
  const input = document.querySelector('[data-search-input]')
  const reset = document.querySelector('[data-search-reset]')
  if (!input || !reset) {
    return
  }

  const sync = () => {
    const hasText = input.value.length > 0
    reset.hidden = !hasText
    input
      .closest('.event-search__field')
      ?.classList.toggle('event-search__field--has-reset', hasText)
  }

  input.addEventListener('input', sync)
  window.addEventListener('pageshow', sync)
  sync()

  reset.addEventListener('click', (event) => {
    if (new URL(window.location.href).searchParams.has('q')) {
      return
    }

    event.preventDefault()
    input.value = ''
    sync()
    input.focus()
  })
}

/**
 * The user menu and the menu drawer, both <details>.
 *
 * <details> already opens and closes from the keyboard. What it lacks is what a
 * menu is expected to do besides: Escape closes it and puts focus back on its
 * toggle, a click anywhere else closes it, and the full-screen menu's own close
 * button works. Closing also plays the way out: the
 * menu gets `data-closing`, the stylesheet animates its panel away, and only
 * then does <details> close. Without JavaScript it simply closes at once.
 */
function initialiseDisclosures() {
  const disclosures = [...document.querySelectorAll('[data-disclosure]')]

  if (disclosures.length === 0) {
    return
  }

  const close = (disclosure, { returnFocus = false } = {}) => {
    if (!disclosure.open || disclosure.hasAttribute('data-closing')) {
      return
    }

    const panel = disclosure.querySelector('[data-disclosure-panel]')
    let finished = false

    const finish = () => {
      if (finished) {
        return
      }

      finished = true
      disclosure.removeAttribute('data-closing')
      disclosure.open = false

      if (returnFocus) {
        disclosure.querySelector('summary')?.focus()
      }
    }

    disclosure.setAttribute('data-closing', '')

    // The panel's own animation ends the close; the links inside it animate
    // too, so their events are ignored. The timeout, the panel's own duration
    // plus a margin, covers a panel that has no animation to end.
    panel?.addEventListener('animationend', function onEnd(event) {
      if (event.target === panel) {
        panel.removeEventListener('animationend', onEnd)
        finish()
      }
    })
    const duration = panel ? Number.parseFloat(getComputedStyle(panel).animationDuration) * 1000 : 0
    setTimeout(finish, (Number.isFinite(duration) ? duration : 0) + 100)
  }

  for (const disclosure of disclosures) {
    disclosure.querySelector('summary')?.addEventListener('click', (event) => {
      if (disclosure.open) {
        event.preventDefault()
        close(disclosure)
      }
    })

    // Shown only now: without JavaScript there is nothing to make it work.
    // `data-enhanced` lets the stylesheet hide the outer toggle while the
    // drawer is open, so there is one close button on screen, not two.
    for (const button of disclosure.querySelectorAll('[data-disclosure-close]')) {
      button.hidden = false
      disclosure.setAttribute('data-enhanced', '')
      button.addEventListener('click', () => close(disclosure, { returnFocus: true }))
    }
  }

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return
    }

    for (const disclosure of disclosures) {
      close(disclosure, { returnFocus: true })
    }
  })

  document.addEventListener('click', (event) => {
    for (const disclosure of disclosures) {
      if (!disclosure.contains(event.target)) {
        close(disclosure)
      }
    }
  })
}

/** The filter fields remain visible without JavaScript; the button adds a compact, animated panel. */
function initialiseEventFilters() {
  const button = document.querySelector('[data-filter-toggle]')
  const panel = document.querySelector('[data-filter-panel]')
  if (!button || !panel) {
    return
  }

  panel.hidden = true
  panel.removeAttribute('data-filter-open')
  button.hidden = false
  let animation
  let closing = false

  const toggle = () => {
    const opening = panel.hidden || closing
    const start = animation
      ? panel.getBoundingClientRect().height
      : panel.hidden
        ? 0
        : panel.scrollHeight
    animation?.cancel()

    const durationToken = getComputedStyle(panel).getPropertyValue('--duration-menu').trim()
    const duration = matchMedia('(prefers-reduced-motion: reduce)').matches
      ? 0
      : Number.parseFloat(durationToken) * (durationToken.endsWith('ms') ? 1 : 1000)

    if (opening) {
      panel.hidden = false
      panel.setAttribute('data-filter-open', '')
    }
    closing = !opening
    button.setAttribute('aria-expanded', String(opening))

    if (duration === 0) {
      panel.hidden = !opening
      if (!opening) {
        panel.removeAttribute('data-filter-open')
      }
      panel.removeAttribute('data-filter-animating')
      closing = false
      animation = undefined
      return
    }

    panel.setAttribute('data-filter-animating', '')
    animation = panel.animate(
      [{ height: `${start}px` }, { height: `${opening ? panel.scrollHeight : 0}px` }],
      {
        duration,
        easing: getComputedStyle(panel).getPropertyValue('--easing-menu').trim(),
        fill: 'forwards',
      },
    )

    animation.onfinish = () => {
      if (!opening) {
        panel.hidden = true
        panel.removeAttribute('data-filter-open')
      }
      panel.removeAttribute('data-filter-animating')
      animation.cancel()
      animation = undefined
      closing = false
    }
  }

  button.addEventListener('click', toggle)

  panel.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !panel.hidden && !closing) {
      toggle()
      button.focus()
    }
  })
}

/** Keep custom filter summaries current and close their menus predictably. */
function initialiseFilterSelects() {
  const selects = [...document.querySelectorAll('[data-filter-select]')]

  for (const select of selects) {
    const summary = select.querySelector('[data-filter-summary]')
    if (summary) {
      select.addEventListener('change', (event) => {
        // Leaving the search field fires `change` too; that is typing, not a choice.
        if (event.target.matches('[data-option-search]')) return
        const checked = [...select.querySelectorAll('input:checked')]
        const name = (input) => {
          const label = input?.closest('label')
          return (label?.querySelector('[data-option-name]') ?? label)?.textContent.trim()
        }
        if (select.hasAttribute('data-single-select')) {
          summary.textContent = name(checked[0])
          select.open = false
        } else if (checked.length === 0) {
          summary.textContent = select.dataset.allLabel
        } else if (checked.length === 1) {
          summary.textContent = name(checked[0])
        } else {
          const label = select.dataset.multipleLabel
          summary.textContent = label.includes(':count')
            ? label.replace(':count', checked.length)
            : `${checked.length} ${label}`
        }
      })
    }

    const search = select.querySelector('[data-option-search]')
    if (search) {
      // Like the other dropdowns, a chosen option stays in the list, checked.
      const empty = select.querySelector('[data-option-empty]')
      search.addEventListener('input', () => {
        const term = search.value.trim().toLocaleLowerCase()
        let visible = 0
        for (const row of select.querySelectorAll('[data-option-row]')) {
          row.hidden = !row.textContent.trim().toLocaleLowerCase().includes(term)
          visible += Number(!row.hidden)
        }
        if (empty) empty.hidden = visible > 0
      })
      // Enter searches the list; it must not submit the surrounding form.
      search.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') event.preventDefault()
      })
    }

    select.addEventListener('toggle', () => {
      if (select.open) search?.focus()
    })

    select.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        select.open = false
        select.querySelector('summary')?.focus()
      }
    })
  }

  document.addEventListener('click', (event) => {
    for (const select of selects) {
      if (!select.contains(event.target)) {
        select.open = false
      }
    }
  })
}

function initialisePlatformRequirement() {
  for (const form of document.querySelectorAll('form:has([data-event-platforms])')) {
    if (form.querySelector('[data-event-step]')) continue
    const select = form.querySelector('[data-event-platforms]')
    const inputs = [...select.querySelectorAll('input[name="platforms[]"]')]
    if (inputs.length === 0) continue
    select.addEventListener('change', () => inputs[0].setCustomValidity(''))
    form.addEventListener('submit', (event) => {
      if (inputs.some((input) => input.checked)) return
      event.preventDefault()
      select.open = true
      inputs[0].setCustomValidity(select.dataset.requiredMessage)
      inputs[0].reportValidity()
    })
  }
}

/** Keep the event form's selected games, categories, platforms, capacity and date controls in sync. */
function initialiseEventCreateForm() {
  const form = document.querySelector('[data-event-create]')
  if (!form) {
    return
  }

  const title = form.querySelector('#title')
  const titleCount = form.querySelector('[data-title-count]')
  if (title && titleCount) {
    const updateTitleCount = () => {
      titleCount.textContent = titleCount.dataset.countLabel
        .replace(':count', String(title.value.length))
        .replace(':max', String(title.maxLength))
    }
    title.addEventListener('input', updateTitleCount)
    updateTitleCount()
  }

  const chipTemplate = form.querySelector('[data-event-chip-template]')
  for (const [selectAttribute, chipAttribute, kind] of [
    ['data-event-games', 'data-event-game-chips', 'game'],
    ['data-event-categories', 'data-event-category-chips', 'category'],
    ['data-event-platforms', 'data-event-platform-chips', 'platform'],
  ]) {
    const select = form.querySelector(`[${selectAttribute}]`)
    const chips = form.querySelector(`[${chipAttribute}]`)
    if (!select || !chips || !chipTemplate) continue

    const syncChips = () => {
      const buttons = [...select.querySelectorAll('input:checked')].map((input) => {
        const name =
          input.closest('label')?.querySelector('span:last-child')?.textContent.trim() ?? ''
        const button = chipTemplate.content.firstElementChild.cloneNode(true)
        button.dataset[`remove${kind[0].toUpperCase()}${kind.slice(1)}`] = input.value
        button.setAttribute('aria-label', select.dataset.removeLabel.replace(':name', name))
        button.querySelector('[data-chip-name]').textContent = name
        const logo = button.querySelector('[data-chip-logo]')
        if (logo) {
          const option = input.closest('label')
          const optionLogo = option?.querySelector('.event-create__game-option-logo')
          const platformIcon = option?.querySelector('.platform-icon')
          if (kind === 'game' && optionLogo) {
            logo.replaceChildren(...[...optionLogo.childNodes].map((node) => node.cloneNode(true)))
            logo.hidden = false
          } else if (kind === 'platform' && platformIcon) {
            logo.replaceChildren(platformIcon.cloneNode(true))
            logo.classList.add('event-create__chip-logo--icon')
            logo.hidden = false
          }
        }
        return button
      })
      chips.replaceChildren(...buttons)
      chips.hidden = buttons.length === 0
    }

    select.addEventListener('change', syncChips)
    chips.addEventListener('click', (event) => {
      const button = event.target.closest(`[data-remove-${kind}]`)
      if (!button) return
      const input = [...select.querySelectorAll('input')].find(
        (option) =>
          option.value === button.dataset[`remove${kind[0].toUpperCase()}${kind.slice(1)}`],
      )
      if (!input) return
      input.checked = false
      input.dispatchEvent(new Event('change', { bubbles: true }))
      select.querySelector('summary')?.focus()
    })
    syncChips()
  }

  const capacityFields = form.querySelector('[data-capacity-fields]')
  const syncCapacity = () => {
    if (!capacityFields) return
    const unlimited = form.querySelector('[data-capacity-mode]:checked')?.value === 'unlimited'
    capacityFields.hidden = unlimited
    for (const input of capacityFields.querySelectorAll('input')) {
      input.disabled = unlimited
      input.required = !unlimited
    }
  }
  form.addEventListener('change', (event) => {
    if (event.target.matches('[data-capacity-mode]')) {
      syncCapacity()
    }
  })
  syncCapacity()

  initialiseLocationSearch(form)
  initialiseSchedulePicker(form)
}

function initialiseEventSteps() {
  const form = document.querySelector('[data-event-create]:has([data-event-step])')
  if (!form) return

  const steps = [...form.querySelectorAll('[data-event-step]')]
  const navigation = form.querySelector('[data-event-steps]')
  const tabs = [...navigation.querySelectorAll('[data-step-tab]')]
  let current = steps.findIndex((step) => step.querySelector('.field__error'))
  if (current < 0) current = 0

  const show = (index, focus = true) => {
    current = index
    steps.forEach((step, stepIndex) => {
      step.hidden = stepIndex !== index
    })
    tabs.forEach((tab, stepIndex) => {
      if (stepIndex === index) tab.setAttribute('aria-current', 'step')
      else tab.removeAttribute('aria-current')
    })
    if (focus) steps[index].querySelector('h2')?.scrollIntoView({ block: 'start' })
  }

  const showError = (step, message) => {
    let error = step.querySelector('[data-step-error]')
    if (!error) {
      error = document.createElement('p')
      error.className = 'field__error'
      error.setAttribute('role', 'alert')
      error.dataset.stepError = ''
      step.querySelector('h2').after(error)
    }
    error.textContent = message
    error.hidden = false
  }

  const validate = (index) => {
    const step = steps[index]
    const error = step.querySelector('[data-step-error]')
    if (error) error.hidden = true
    if (index === 0) {
      for (const select of step.querySelectorAll(
        '[data-event-games], [data-event-categories], [data-event-platforms]',
      )) {
        if (select.querySelector('input:checked')) continue
        show(index)
        select.open = true
        showError(step, select.dataset.requiredMessage)
        select.querySelector('summary')?.focus()
        return false
      }
    }
    const invalid = [...step.querySelectorAll('input[required], textarea[required]')].find(
      (field) => field.form === form && !field.disabled && !field.checkValidity(),
    )
    if (invalid) {
      show(index)
      if (invalid.closest('[data-schedule-fallback]')?.hidden) {
        showError(step, form.dataset.scheduleRequired)
        step.querySelector('[data-schedule-details]')?.setAttribute('open', '')
        step.querySelector('[data-schedule-summary]')?.focus()
      } else invalid.reportValidity()
      return false
    }
    return true
  }

  navigation.hidden = false
  for (const actions of form.querySelectorAll('[data-step-actions]')) actions.hidden = false
  for (const button of form.querySelectorAll('[data-step-back]')) button.hidden = false
  form.noValidate = true
  show(current, false)

  form.addEventListener('click', (event) => {
    const next = event.target.closest('[data-step-next]')
    const back = event.target.closest('[data-step-back]')
    const tab = event.target.closest('[data-step-tab]')
    if (next && validate(current)) show(Math.min(current + 1, steps.length - 1))
    if (back) show(Math.max(current - 1, 0))
    if (tab) {
      const target = Number(tab.dataset.stepTab)
      if (target > current) {
        for (let index = current; index < target; index += 1) {
          if (!validate(index)) return
        }
      }
      show(target)
    }
  })

  form.addEventListener('submit', (event) => {
    for (let index = 0; index < steps.length; index += 1) {
      if (validate(index)) continue
      event.preventDefault()
      return
    }
  })
}

/**
 * Modals: native <dialog>, opened by `command="show-modal"` buttons.
 *
 * Two gaps are filled here. A browser without invoker commands gets the same
 * behaviour from a click handler. And a dialog the server rendered open — after a
 * failed submit from inside it — is reopened as a real modal, with focus going
 * back to the button that would have opened it once it closes.
 */
function initialiseModals() {
  const supportsInvokers = 'command' in HTMLButtonElement.prototype

  if (!supportsInvokers) {
    for (const button of document.querySelectorAll('button[commandfor]')) {
      button.addEventListener('click', () => {
        const dialog = document.getElementById(button.getAttribute('commandfor'))
        const command = button.getAttribute('command')

        if (!(dialog instanceof HTMLDialogElement)) {
          return
        }

        if (command === 'show-modal' && !dialog.open) {
          dialog.showModal()
        } else if (command === 'close') {
          dialog.close()
        }
      })
    }
  }

  for (const dialog of document.querySelectorAll('dialog[data-modal]')) {
    // A click on the backdrop lands on the <dialog> itself; one on its content
    // lands on a child. `closedby="any"` does this natively where supported.
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) {
        dialog.close()
      }
    })

    if (dialog.hasAttribute('data-modal-show')) {
      const opener = document.querySelector(`[commandfor="${dialog.id}"][command="show-modal"]`)

      dialog.close()
      dialog.showModal()
      dialog.addEventListener('close', () => opener?.focus(), { once: true })
    }
  }
}

/**
 * "Saved." beside a form's button fades out after two seconds, as it does in
 * Breeze. It is a role="status" region, so it has been announced by then.
 * Without JavaScript the message simply stays.
 */
function initialiseTransientMessages() {
  for (const message of document.querySelectorAll('[data-transient]')) {
    setTimeout(() => {
      message.dataset.transient = 'done'
      message.addEventListener('transitionend', () => (message.hidden = true), { once: true })
    }, 2000)
  }
}

/**
 * Update only the participant list when its page changes. The links still work
 * normally without JavaScript and carry a fragment back to the list.
 */
function initialiseParticipantPagination() {
  if (!document.querySelector('[data-participants]')) {
    return
  }

  const pagePath = window.location.pathname
  let pendingRequest

  const showPage = async (url, addHistory) => {
    pendingRequest?.abort()
    const request = new AbortController()
    pendingRequest = request
    document.querySelector('[data-participants]')?.setAttribute('aria-busy', 'true')

    try {
      const response = await fetch(url, { signal: request.signal })
      if (!response.ok) {
        throw new Error('Could not load participants')
      }

      const page = new DOMParser().parseFromString(await response.text(), 'text/html')
      const current = document.querySelector('[data-participants]')
      const next = page.querySelector('[data-participants]')
      if (!current || !next) {
        throw new Error('The participant list is missing')
      }

      current.replaceWith(next)
      if (addHistory) {
        window.history.pushState(null, '', url)
      }

      next.querySelector('[data-participants-heading]')?.focus({ preventScroll: true })
      next.scrollIntoView({ block: 'nearest' })
    } catch (error) {
      if (error.name !== 'AbortError') {
        if (addHistory) {
          window.location.assign(url)
        } else {
          window.location.reload()
        }
      }
    } finally {
      if (pendingRequest === request) {
        pendingRequest = undefined
        document.querySelector('[data-participants]')?.removeAttribute('aria-busy')
      }
    }
  }

  document.addEventListener('click', (event) => {
    if (
      event.defaultPrevented ||
      event.button !== 0 ||
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      event.altKey ||
      !(event.target instanceof Element)
    ) {
      return
    }

    const link = event.target.closest('[data-participant-pages] a')
    if (!link) {
      return
    }

    event.preventDefault()
    showPage(link.href, true)
  })

  window.addEventListener('popstate', () => {
    if (window.location.pathname === pagePath) {
      showPage(window.location.href, false)
    }
  })
}

document.addEventListener('DOMContentLoaded', () => {
  initialiseDismissibleAlerts()
  initialiseEventSearchReset()
  initialiseDisclosures()
  initialiseEventFilters()
  initialiseFilterSelects()
  initialisePlatformRequirement()
  initialiseEventCreateForm()
  initialiseEventSteps()
  initialiseGameCatalog()
  initialiseModals()
  initialiseTransientMessages()
  initialiseParticipantPagination()
})
