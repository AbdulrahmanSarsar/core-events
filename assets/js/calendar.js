/**
 * Core Events Pro - Frontend Calendar JavaScript.
 * 
 * Handles the rendering, navigation, and API fetching for the interactive
 * monthly event calendar. Includes a modal for displaying event details.
 *
 * @package CoreEventsPro\Assets
 * @since 4.0.0
 */

jQuery(document).ready(function($) {
    'use strict';

    /**
     * Translation Helper Function.
     * 
     * Safely utilizes WordPress wp.i18n for translating JavaScript strings.
     * 
     * @param {string} text   The string to translate.
     * @param {string} domain The text domain.
     * @return {string}       The translated string.
     */
    function __(text, domain) {
        if (typeof wp !== 'undefined' && wp.i18n && wp.i18n.__) {
            return wp.i18n.__(text, domain);
        }
        return text;
    }

    /**
     * HTML Escaping Helper.
     * 
     * Security measure: Prevents Cross-Site Scripting (XSS) attacks by escaping 
     * unsafe characters before rendering dynamic HTML content from the API.
     * 
     * @param {string} unsafe The unsafe string.
     * @return {string}       The escaped HTML string.
     */
    function escapeHTML(unsafe) {
        if (!unsafe) return '';
        return String(unsafe)
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }

    const wrapper = $('.cep-calendar-wrapper');

    // Security/Bail: If the calendar wrapper doesn't exist on the page, do nothing.
    if (!wrapper.length) return;

    let currentDate = new Date();

    // Modal Elements
    const modal      = $('#cep-modal');
    const modalTitle = $('#cep-modal-title');
    const modalDate  = $('#cep-modal-date');
    const modalLink  = $('#cep-modal-link');
    const modalClose = $('#cep-close-modal');

    /**
     * Load Calendar Data from the REST API.
     * 
     * Updates the month label and fetches events based on the current date,
     * category, and type filters.
     */
    function loadCalendar() {
        const year  = currentDate.getFullYear();
        const month = currentDate.getMonth() + 1;
        const formattedMonth = `${year}-${String(month).padStart(2, '0')}`;
        
        // Update the header label (e.g., "September 2025")
        $('#cep-month-label').text(currentDate.toLocaleDateString('default', { month: 'long', year: 'numeric' }));
        
        // Show loading state securely
        const loadingText = __('Loading...', 'core-events-pro');
        $('#cep-cal-grid').html('<div style="grid-column:1/-1; padding:20px; text-align:center; color:#888;">' + escapeHTML(loadingText) + '</div>');

        // Fetch Data from API
        $.get(cepData.api_url + 'calendar', { 
            month: formattedMonth, 
            category: wrapper.data('category'),
            type: wrapper.data('type')
        }, function(events) {
            console.log('Events found:', events.length); // Console debug check
            renderGrid(year, month - 1, events);
        }).fail(function() {
            const errorText = __('Error loading events.', 'core-events-pro');
            $('#cep-cal-grid').html('<div style="grid-column:1/-1; text-align:center; color:red;">' + escapeHTML(errorText) + '</div>');
        });
    }

    /**
     * Render the Calendar Grid.
     * 
     * Generates the day blocks and places events inside their respective dates.
     * 
     * @param {number} year   The current year.
     * @param {number} month  The current zero-indexed month.
     * @param {Array}  events The array of event objects from the API.
     */
    function renderGrid(year, month, events) {
        const grid = $('#cep-cal-grid');
        grid.empty();

        const firstDay    = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        // Inject empty blocks for days before the 1st of the month
        for (let i = 0; i < firstDay; i++) {
            grid.append('<div class="cep-day empty"></div>');
        }

        // Loop through each day of the month
        for (let day = 1; day <= daysInMonth; day++) {
            const cellDate = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const isToday  = new Date().toDateString() === new Date(year, month, day).toDateString();
            
            let html = `<div class="cep-day ${isToday ? 'today' : ''}">
                            <span class="cep-day-num">${day}</span>
                            <div class="cep-events-container">`;
            
            // 1. Filter events belonging to this specific day
            let dayEvents = events.filter(ev => {
                if (!ev.start) return false;
                let s = ev.start.substring(0, 10);
                let e = ev.end ? ev.end.substring(0, 10) : s;
                return (cellDate >= s && cellDate <= e);
            });

            // 2. Smart Sorting: Main events first, then sub events
            dayEvents.sort((a, b) => {
                if (a.type === 'main_event' && b.type !== 'main_event') return -1;
                if (a.type !== 'main_event' && b.type === 'main_event') return 1;
                return 0; // Same type
            });

            // 3. Draw the events (With strict HTML escaping)
            dayEvents.forEach(ev => {
                let typeClass = (ev.type === 'main_event') ? 'cep-is-main' : 'cep-is-sub';
                
                // For sub events, we don't use border color, we leave it to CSS
                let style = (ev.type === 'main_event') ? `border-left-color: ${escapeHTML(ev.color)};` : '';
                
                html += `<a href="#" class="cep-event-link ${typeClass}" 
                           style="${style}" 
                           title="${escapeHTML(ev.title)}"
                           data-title="${escapeHTML(ev.title)}"
                           data-date="${escapeHTML(ev.start)}"
                           data-url="${escapeHTML(ev.url)}"
                           data-loc="${escapeHTML(ev.location || '')}">
                           ${escapeHTML(ev.title)}
                        </a>`;
            });

            html += `</div></div>`;
            grid.append(html);
        }
    }

    // Navigation Listeners
    $(document).on('click', '#cep-prev', function(e) { 
        e.preventDefault(); 
        currentDate.setMonth(currentDate.getMonth() - 1); 
        loadCalendar(); 
    });

    $(document).on('click', '#cep-next', function(e) { 
        e.preventDefault(); 
        currentDate.setMonth(currentDate.getMonth() + 1); 
        loadCalendar(); 
    });

    $(document).on('click', '#cep-today', function(e) { 
        e.preventDefault(); 
        currentDate = new Date(); 
        loadCalendar(); 
    });

    // Open Modal Listener
    $(document).on('click', '.cep-event-link', function(e) {
        e.preventDefault();
        const link = $(this);
        
        // Populate modal data safely using .text() (prevents XSS)
        modalTitle.text(link.data('title'));
        
        let dateText = link.data('date');
        
        // Add location to the text if it exists
        if (link.data('loc')) {
            dateText += ' | 📍 ' + link.data('loc');
        }
        modalDate.text(dateText);
        
        // Set URL safely
        modalLink.attr('href', link.data('url'));
        
        // Show modal smoothly
        modal.fadeIn(200);
    });

    // Close Modal Listeners
    $(document).on('click', '#cep-close-modal', function() { 
        modal.fadeOut(200); 
    });

    $(window).on('click', function(e) { 
        if ($(e.target).is(modal)) {
            modal.fadeOut(200); 
        }
    });

    // Initialize the calendar on page load
    loadCalendar();
});
