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

document.addEventListener('DOMContentLoaded', () => {
  initialiseDismissibleAlerts()
  initialiseDisclosures()
  initialiseModals()
  initialiseTransientMessages()
})
