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
 * The user menu and the hamburger menu, both <details>.
 *
 * <details> already opens and closes from the keyboard. What it lacks is what a
 * menu is expected to do besides: Escape closes it and puts focus back on its
 * toggle, and a click anywhere else closes it.
 */
function initialiseDisclosures() {
  const disclosures = [...document.querySelectorAll('[data-disclosure]')]

  if (disclosures.length === 0) {
    return
  }

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return
    }

    for (const disclosure of disclosures) {
      if (disclosure.open) {
        disclosure.open = false
        disclosure.querySelector('summary')?.focus()
      }
    }
  })

  document.addEventListener('click', (event) => {
    for (const disclosure of disclosures) {
      if (disclosure.open && !disclosure.contains(event.target)) {
        disclosure.open = false
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
