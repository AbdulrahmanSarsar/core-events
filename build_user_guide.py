"""
Generate the Core Events Pro - User Guide PDF.

This produces a professional, end-user oriented manual (no code, no developer
jargon) explaining every feature of the plugin and how to use it.
"""

from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import cm, mm
from reportlab.lib.colors import HexColor, white, black
from reportlab.lib.enums import TA_CENTER, TA_LEFT, TA_JUSTIFY
from reportlab.platypus import (
    BaseDocTemplate, PageTemplate, Frame,
    Paragraph, Spacer, PageBreak, Table, TableStyle,
    KeepTogether, ListFlowable, ListItem, NextPageTemplate
)
from reportlab.pdfgen import canvas


# ---------------------------------------------------------------------------
# Color palette (matches the plugin's UI: blue + slate)
# ---------------------------------------------------------------------------
PRIMARY      = HexColor('#2563eb')
PRIMARY_DARK = HexColor('#1d4ed8')
DARK         = HexColor('#1e293b')
SLATE        = HexColor('#475569')
MUTED        = HexColor('#64748b')
LIGHT_BG     = HexColor('#f8fafc')
BORDER       = HexColor('#e2e8f0')
SUCCESS      = HexColor('#059669')
SUCCESS_BG   = HexColor('#ecfdf5')
WARNING      = HexColor('#d97706')
WARNING_BG   = HexColor('#fffbeb')
DANGER       = HexColor('#dc2626')
DANGER_BG    = HexColor('#fef2f2')
INFO_BG      = HexColor('#eff6ff')


# ---------------------------------------------------------------------------
# Styles
# ---------------------------------------------------------------------------
styles = getSampleStyleSheet()

styles.add(ParagraphStyle(
    'CoverTitle', parent=styles['Title'],
    fontName='Helvetica-Bold', fontSize=44, leading=52,
    textColor=white, alignment=TA_CENTER, spaceAfter=10
))
styles.add(ParagraphStyle(
    'CoverSubtitle', parent=styles['Normal'],
    fontName='Helvetica', fontSize=18, leading=24,
    textColor=HexColor('#cbd5e1'), alignment=TA_CENTER, spaceAfter=30
))
styles.add(ParagraphStyle(
    'CoverMeta', parent=styles['Normal'],
    fontName='Helvetica', fontSize=12, leading=16,
    textColor=HexColor('#94a3b8'), alignment=TA_CENTER
))

styles.add(ParagraphStyle(
    'ChapterNum', parent=styles['Normal'],
    fontName='Helvetica-Bold', fontSize=12, leading=14,
    textColor=PRIMARY, alignment=TA_LEFT, spaceAfter=4
))
styles.add(ParagraphStyle(
    'ChapterTitle', parent=styles['Heading1'],
    fontName='Helvetica-Bold', fontSize=26, leading=30,
    textColor=DARK, alignment=TA_LEFT,
    spaceBefore=0, spaceAfter=14
))
styles.add(ParagraphStyle(
    'SectionTitle', parent=styles['Heading2'],
    fontName='Helvetica-Bold', fontSize=15, leading=20,
    textColor=DARK, alignment=TA_LEFT,
    spaceBefore=14, spaceAfter=6
))
styles.add(ParagraphStyle(
    'SubTitle', parent=styles['Heading3'],
    fontName='Helvetica-Bold', fontSize=12, leading=16,
    textColor=PRIMARY, alignment=TA_LEFT,
    spaceBefore=10, spaceAfter=4
))
styles.add(ParagraphStyle(
    'Body', parent=styles['Normal'],
    fontName='Helvetica', fontSize=10.5, leading=15,
    textColor=DARK, alignment=TA_JUSTIFY,
    spaceAfter=6
))
styles.add(ParagraphStyle(
    'BodyLeft', parent=styles['Normal'],
    fontName='Helvetica', fontSize=10.5, leading=15,
    textColor=DARK, alignment=TA_LEFT,
    spaceAfter=6
))
styles.add(ParagraphStyle(
    'CepBullet', parent=styles['Normal'],
    fontName='Helvetica', fontSize=10.5, leading=15,
    textColor=DARK, alignment=TA_LEFT, leftIndent=14,
    bulletIndent=0, spaceAfter=3
))
styles.add(ParagraphStyle(
    'CepNumbered', parent=styles['Normal'],
    fontName='Helvetica', fontSize=10.5, leading=15,
    textColor=DARK, alignment=TA_LEFT, leftIndent=18,
    bulletIndent=0, spaceAfter=3
))
styles.add(ParagraphStyle(
    'CepCaption', parent=styles['Normal'],
    fontName='Helvetica-Oblique', fontSize=9, leading=12,
    textColor=MUTED, alignment=TA_LEFT, spaceAfter=8
))
styles.add(ParagraphStyle(
    'CepCode', parent=styles['Normal'],
    fontName='Courier', fontSize=10, leading=14,
    textColor=DARK, backColor=LIGHT_BG,
    borderColor=BORDER, borderWidth=0.5, borderPadding=6,
    spaceBefore=4, spaceAfter=8
))
styles.add(ParagraphStyle(
    'TocItem', parent=styles['Normal'],
    fontName='Helvetica', fontSize=11, leading=18,
    textColor=DARK, alignment=TA_LEFT, leftIndent=4,
    spaceAfter=2
))
styles.add(ParagraphStyle(
    'TocTitle', parent=styles['Heading1'],
    fontName='Helvetica-Bold', fontSize=24, leading=28,
    textColor=DARK, spaceAfter=20
))
styles.add(ParagraphStyle(
    'CalloutBody', parent=styles['Normal'],
    fontName='Helvetica', fontSize=10, leading=14,
    textColor=DARK, alignment=TA_LEFT, spaceAfter=0
))


# ---------------------------------------------------------------------------
# Reusable building blocks
# ---------------------------------------------------------------------------
def hr(width=17 * cm, color=BORDER, thickness=0.6):
    """Thin horizontal rule used between sections."""
    t = Table([['']], colWidths=[width], rowHeights=[thickness])
    t.setStyle(TableStyle([
        ('LINEBELOW', (0, 0), (-1, -1), thickness, color),
    ]))
    return t


def callout(label, body_text, bg, accent, label_color=None):
    """A colored callout box (Tip / Note / Warning)."""
    if label_color is None:
        label_color = accent
    inner = [
        Paragraph(f'<font color="{label_color.hexval()}"><b>{label}</b></font>',
                  styles['CalloutBody']),
        Spacer(1, 3),
        Paragraph(body_text, styles['CalloutBody']),
    ]
    t = Table([[inner]], colWidths=[16.5 * cm])
    t.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), bg),
        ('LINEBEFORE',  (0, 0), (0, -1), 3, accent),
        ('LEFTPADDING', (0, 0), (-1, -1), 12),
        ('RIGHTPADDING',(0, 0), (-1, -1), 12),
        ('TOPPADDING',  (0, 0), (-1, -1), 8),
        ('BOTTOMPADDING',(0, 0), (-1, -1), 8),
    ]))
    return t


def tip_box(text):
    return callout('TIP', text, INFO_BG, PRIMARY)


def note_box(text):
    return callout('NOTE', text, LIGHT_BG, MUTED, label_color=SLATE)


def warn_box(text):
    return callout('WARNING', text, WARNING_BG, WARNING)


def success_box(text):
    return callout('GOOD TO KNOW', text, SUCCESS_BG, SUCCESS)


def shortcode_box(code, description):
    """A two-column row showing a shortcode (left) and what it does (right)."""
    code_p = Paragraph(f'<font face="Courier"><b>{code}</b></font>',
                       styles['CalloutBody'])
    desc_p = Paragraph(description, styles['CalloutBody'])
    t = Table([[code_p, desc_p]], colWidths=[6.2 * cm, 10.3 * cm])
    t.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (0, 0), LIGHT_BG),
        ('BOX',        (0, 0), (-1, -1), 0.5, BORDER),
        ('LINEAFTER',  (0, 0), (0, -1), 0.5, BORDER),
        ('VALIGN',     (0, 0), (-1, -1), 'MIDDLE'),
        ('LEFTPADDING',(0, 0), (-1, -1), 10),
        ('RIGHTPADDING',(0, 0), (-1, -1), 10),
        ('TOPPADDING', (0, 0), (-1, -1), 8),
        ('BOTTOMPADDING',(0, 0), (-1, -1), 8),
    ]))
    return t


def key_value_table(rows, col_widths=(5.0 * cm, 11.5 * cm)):
    """Two-column table: bold key on the left, description on the right."""
    data = []
    for k, v in rows:
        data.append([
            Paragraph(f'<b>{k}</b>', styles['CalloutBody']),
            Paragraph(v, styles['CalloutBody']),
        ])
    t = Table(data, colWidths=list(col_widths))
    t.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (0, -1), LIGHT_BG),
        ('VALIGN',     (0, 0), (-1, -1), 'TOP'),
        ('LEFTPADDING',(0, 0), (-1, -1), 10),
        ('RIGHTPADDING',(0, 0), (-1, -1), 10),
        ('TOPPADDING', (0, 0), (-1, -1), 8),
        ('BOTTOMPADDING',(0, 0), (-1, -1), 8),
        ('LINEBELOW',  (0, 0), (-1, -1), 0.4, BORDER),
        ('BOX',        (0, 0), (-1, -1), 0.5, BORDER),
    ]))
    return t


def numbered_list(items):
    flowables = [
        ListItem(Paragraph(item, styles['BodyLeft']),
                 leftIndent=10, value=i + 1)
        for i, item in enumerate(items)
    ]
    return ListFlowable(
        flowables, bulletType='1', start='1',
        bulletFontName='Helvetica-Bold', bulletFontSize=10.5,
        bulletColor=PRIMARY, leftIndent=18, bulletDedent=14,
    )


def bullet_list(items):
    flowables = [ListItem(Paragraph(item, styles['BodyLeft']),
                          leftIndent=10) for item in items]
    return ListFlowable(
        flowables, bulletType='bullet', start='•',
        bulletFontSize=10.5, bulletColor=PRIMARY,
        leftIndent=18, bulletDedent=10,
    )


def chapter_header(num, title):
    """Big chapter header (number + title with colored block)."""
    return [
        Spacer(1, 4),
        Paragraph(f'CHAPTER {num}', styles['ChapterNum']),
        Paragraph(title, styles['ChapterTitle']),
        hr(width=4.5 * cm, color=PRIMARY, thickness=2),
        Spacer(1, 14),
    ]


def section(title):
    return [Spacer(1, 4), Paragraph(title, styles['SectionTitle'])]


def subsection(title):
    return Paragraph(title, styles['SubTitle'])


def para(text):
    return Paragraph(text, styles['Body'])


# ---------------------------------------------------------------------------
# Page templates
# ---------------------------------------------------------------------------
PAGE_W, PAGE_H = A4
MARGIN = 2.0 * cm


def _draw_cover(canv, doc):
    """Cover page with full-bleed dark gradient background."""
    canv.saveState()
    # Full background
    canv.setFillColor(DARK)
    canv.rect(0, 0, PAGE_W, PAGE_H, fill=1, stroke=0)
    # Top accent bar
    canv.setFillColor(PRIMARY)
    canv.rect(0, PAGE_H - 1.4 * cm, PAGE_W, 1.4 * cm, fill=1, stroke=0)
    # Bottom accent bar
    canv.setFillColor(PRIMARY)
    canv.rect(0, 0, PAGE_W, 0.6 * cm, fill=1, stroke=0)
    # Footer text
    canv.setFillColor(white)
    canv.setFont('Helvetica', 9)
    canv.drawString(MARGIN, 0.22 * cm, 'Core Events Pro  -  User Guide')
    canv.drawRightString(PAGE_W - MARGIN, 0.22 * cm, 'Version 1.0.0')
    canv.restoreState()


def _draw_chrome(canv, doc):
    """Standard page chrome: top hairline + footer with page number."""
    canv.saveState()
    # Top hairline
    canv.setStrokeColor(BORDER)
    canv.setLineWidth(0.4)
    canv.line(MARGIN, PAGE_H - MARGIN + 0.6 * cm,
              PAGE_W - MARGIN, PAGE_H - MARGIN + 0.6 * cm)
    # Header text (running title)
    canv.setFont('Helvetica', 8.5)
    canv.setFillColor(MUTED)
    canv.drawString(MARGIN, PAGE_H - MARGIN + 0.78 * cm,
                    'CORE EVENTS PRO  /  USER GUIDE')
    canv.drawRightString(PAGE_W - MARGIN, PAGE_H - MARGIN + 0.78 * cm,
                         'Version 1.0.0')
    # Footer hairline
    canv.setStrokeColor(BORDER)
    canv.line(MARGIN, MARGIN - 0.55 * cm,
              PAGE_W - MARGIN, MARGIN - 0.55 * cm)
    # Page number
    canv.setFont('Helvetica', 9)
    canv.setFillColor(MUTED)
    canv.drawRightString(PAGE_W - MARGIN, MARGIN - 1.0 * cm,
                         f'Page {canv.getPageNumber()}')
    canv.drawString(MARGIN, MARGIN - 1.0 * cm,
                    'www.your-site.example  /  Support')
    canv.restoreState()


def build_doc(path):
    doc = BaseDocTemplate(
        path, pagesize=A4,
        leftMargin=MARGIN, rightMargin=MARGIN,
        topMargin=MARGIN, bottomMargin=MARGIN,
        title='Core Events Pro - User Guide',
        author='Core Events Pro',
        subject='Plugin user manual',
    )
    cover_frame = Frame(0, 0, PAGE_W, PAGE_H,
                        leftPadding=0, rightPadding=0,
                        topPadding=0, bottomPadding=0,
                        id='cover')
    body_frame  = Frame(MARGIN, MARGIN,
                        PAGE_W - 2 * MARGIN, PAGE_H - 2 * MARGIN,
                        leftPadding=0, rightPadding=0,
                        topPadding=0, bottomPadding=0,
                        id='body')
    doc.addPageTemplates([
        PageTemplate(id='Cover', frames=[cover_frame], onPage=_draw_cover),
        PageTemplate(id='Body',  frames=[body_frame],  onPage=_draw_chrome),
    ])
    return doc


# ---------------------------------------------------------------------------
# Cover page content
# ---------------------------------------------------------------------------
def cover_content():
    story = []
    story.append(Spacer(1, 7 * cm))
    # Eyebrow
    story.append(Paragraph(
        '<font color="#38bdf8"><b>WORDPRESS PLUGIN</b></font>',
        ParagraphStyle('eyebrow', parent=styles['Normal'],
                       fontName='Helvetica-Bold', fontSize=11,
                       leading=14, alignment=TA_CENTER,
                       textColor=HexColor('#38bdf8'), spaceAfter=12)
    ))
    story.append(Paragraph('Core Events Pro', styles['CoverTitle']))
    story.append(Paragraph(
        'The Ultimate Event Management System',
        styles['CoverSubtitle']
    ))
    story.append(Spacer(1, 1.2 * cm))
    story.append(Paragraph(
        'USER GUIDE',
        ParagraphStyle('coverlabel', parent=styles['Normal'],
                       fontName='Helvetica-Bold', fontSize=14,
                       leading=18, alignment=TA_CENTER,
                       textColor=white, spaceAfter=6)
    ))
    story.append(Paragraph(
        'A complete, non-technical guide to setting up,<br/>'
        'running, and growing events with the plugin.',
        styles['CoverMeta']
    ))
    story.append(Spacer(1, 4 * cm))
    story.append(Paragraph(
        'Version 1.0.0  /  Compatible with WordPress 5.8+  /  PHP 7.4+',
        styles['CoverMeta']
    ))
    return story


# ---------------------------------------------------------------------------
# Body content
# ---------------------------------------------------------------------------
def toc_content():
    story = [Paragraph('Table of Contents', styles['TocTitle']),
             hr(width=6 * cm, color=PRIMARY, thickness=2),
             Spacer(1, 14)]
    items = [
        ('1',  'Introduction'),
        ('2',  'Installing & Activating the Plugin'),
        ('3',  'The Setup Wizard'),
        ('4',  'A Quick Tour of the Admin Menu'),
        ('5',  'Plugin Settings'),
        ('6',  'Creating Your First Event'),
        ('7',  'Event Media: Banner, Gallery & Video'),
        ('8',  'Event Location & Venue Conflict Prevention'),
        ('9',  'Categories, Types & Tags'),
        ('10', 'Sub-Events (Sessions and Workshops)'),
        ('11', 'Recurring Events'),
        ('12', 'Free RSVP & the Smart Waitlist'),
        ('13', 'Paid Tickets with WooCommerce'),
        ('14', 'QR Code Tickets & Door Check-in'),
        ('15', 'Managing Attendees'),
        ('16', 'Bulk Tools: Clone, Bulk Edit, CSV Import'),
        ('17', 'Displaying Events on Your Website (Shortcodes)'),
        ('18', 'Email Notifications'),
        ('19', 'Activating Your License'),
        ('20', 'Tips, Best Practices & Workflows'),
        ('21', 'Troubleshooting & FAQ'),
        ('22', 'Glossary'),
    ]
    rows = []
    for num, title in items:
        rows.append([
            Paragraph(f'<font color="{PRIMARY.hexval()}"><b>{num}.</b></font>',
                      styles['TocItem']),
            Paragraph(title, styles['TocItem']),
        ])
    t = Table(rows, colWidths=[1.0 * cm, 15.5 * cm])
    t.setStyle(TableStyle([
        ('VALIGN',     (0, 0), (-1, -1), 'TOP'),
        ('LEFTPADDING',(0, 0), (-1, -1), 0),
        ('RIGHTPADDING',(0, 0), (-1, -1), 0),
        ('TOPPADDING', (0, 0), (-1, -1), 4),
        ('BOTTOMPADDING',(0, 0), (-1, -1), 4),
        ('LINEBELOW',  (0, 0), (-1, -1), 0.3, BORDER),
    ]))
    story.append(t)
    return story


# --- chapter builders ----------------------------------------------------------
def ch_introduction():
    s = chapter_header(1, 'Introduction')
    s.append(para(
        'Core Events Pro is a complete event management toolkit for WordPress. '
        'It lets you create events, schedule sessions, sell tickets, collect '
        'free RSVPs, manage a waitlist, send confirmation and reminder emails, '
        'and check attendees in at the door using QR codes. Everything happens '
        'inside your WordPress admin, with no external services to configure.'
    ))
    s += section('What can you build with it?')
    s.append(bullet_list([
        'A single landing page for one workshop or webinar.',
        'A multi-day conference with parallel sessions and speakers.',
        'A recurring weekly meetup with automatic reminders.',
        'A paid event with VIP, Standard and Free ticket tiers.',
        'A series of community events organized by category.',
    ]))
    s += section('Who is this guide for?')
    s.append(para(
        'This guide is written for the people who actually run events: '
        'organizers, marketing teams, community managers and site editors. '
        'You do not need to read code or know PHP. If you can use the '
        'WordPress dashboard, you can run Core Events Pro.'
    ))
    s += section('How this guide is organized')
    s.append(para(
        'The first chapters walk you through installation and the very first '
        'event you will create. Later chapters cover one feature each, so you '
        'can jump directly to whatever you are working on - tickets, the '
        'calendar, attendees, or emails.'
    ))
    s.append(Spacer(1, 8))
    s.append(tip_box(
        'Read Chapters 2 to 6 in order the first time. After that, treat the '
        'guide as a reference and open the chapter you need.'
    ))
    return s


def ch_install():
    s = chapter_header(2, 'Installing & Activating the Plugin')
    s += section('Before you start')
    s.append(para('You will need:'))
    s.append(bullet_list([
        'A working WordPress site (version 5.8 or newer).',
        'PHP 7.4 or newer on your hosting.',
        'Administrator access to the WordPress dashboard.',
        'The plugin ZIP file (core-events-pro.zip).',
        'Optional: WooCommerce installed if you want to sell paid tickets.',
    ]))
    s += section('Step 1 - Upload the plugin')
    s.append(numbered_list([
        'Log in to your WordPress dashboard.',
        'In the left menu, click <b>Plugins -> Add New</b>.',
        'Click the <b>Upload Plugin</b> button at the top of the page.',
        'Click <b>Choose File</b> and select <b>core-events-pro.zip</b>.',
        'Click <b>Install Now</b> and wait for the upload to complete.',
    ]))
    s += section('Step 2 - Activate it')
    s.append(numbered_list([
        'After installation, click the blue <b>Activate Plugin</b> button.',
        'You will be redirected automatically to the Setup Wizard.',
        'Confirm that a new menu item called <b>Events Pro</b> appears in '
        'the left sidebar.',
    ]))
    s.append(Spacer(1, 6))
    s.append(success_box(
        'On activation the plugin creates a database table for attendees, '
        'adds a new "Manage Events" capability for administrators, and '
        'registers all required custom post types automatically. You do not '
        'need to do anything manually.'
    ))
    s += section('Step 3 - Verify the installation')
    s.append(para('You should see the following in your admin sidebar:'))
    s.append(bullet_list([
        '<b>Events Pro</b> - the main menu (calendar icon).',
        '<b>Dashboard</b> - quick stats and CSV import.',
        '<b>All Main Events</b> - your event list.',
        '<b>Add Event</b> - to create a new event.',
        '<b>Sub Events</b> - sessions or workshops.',
        '<b>Attendees</b> - everyone who registered.',
        '<b>Settings & Help</b> - configuration and shortcodes.',
        '<b>License</b> - to activate your purchase code.',
    ]))
    return s


def ch_wizard():
    s = chapter_header(3, 'The Setup Wizard')
    s.append(para(
        'After activation the plugin opens a friendly 3-step wizard. It is '
        'optional - you can skip it - but it takes about two minutes and '
        'configures the most important settings up front.'
    ))
    s += section('Step 1 - Essential features')
    s.append(para('Choose what your event pages will display:'))
    s.append(bullet_list([
        '<b>Show Event Time</b> - turn on if your events use specific hours, '
        'turn off for whole-day events.',
        '<b>Live Countdown Timer</b> - shows a "starts in 3d 12h 4m" timer '
        'on upcoming events. Great for landing pages.',
        '<b>Add to Calendar Button</b> - lets visitors download an .ics file '
        'they can import into Google Calendar, Outlook or Apple Calendar.',
    ]))
    s += section('Step 2 - Venue management')
    s.append(para(
        'Choose how you will enter event locations:'
    ))
    s.append(bullet_list([
        '<b>Free Text</b> - type any address for each event. Easiest if your '
        'events happen all over the place.',
        '<b>Predefined Venues</b> - enter your list of rooms or halls once '
        '(one per line) and pick from a dropdown when creating events. The '
        'plugin will then warn you if you try to book the same venue at the '
        'same time twice.',
    ]))
    s.append(Spacer(1, 4))
    s.append(tip_box(
        'If you run a venue, conference center, or campus, choose Predefined '
        'Venues. The conflict checker alone will save you from many '
        'double-bookings.'
    ))
    s += section('Step 3 - You are all set')
    s.append(para(
        'The final screen confirms your settings and offers a big button to '
        'create your first event. You can change every option later from '
        '<b>Events Pro -> Settings & Help</b>.'
    ))
    return s


def ch_admin_tour():
    s = chapter_header(4, 'A Quick Tour of the Admin Menu')
    s.append(para(
        'When the plugin is active, a single new section called <b>Events Pro</b> '
        'appears in the WordPress sidebar. Everything event-related lives here.'
    ))
    s.append(Spacer(1, 6))
    s.append(key_value_table([
        ('Dashboard',
         'Total events, registrations and check-ins at a glance, plus the '
         'CSV import tool.'),
        ('All Main Events',
         'Lists every event you have created. Lets you edit, clone, or '
         'delete in bulk.'),
        ('Add Event',
         'Opens the editor to create a new main event.'),
        ('Sub Events',
         'A separate list of sessions, workshops or talks attached to '
         'main events.'),
        ('Event Categories / Types / Tags',
         'Three taxonomies you can use to organize events.'),
        ('Attendees',
         'A searchable, filterable table of every person who registered, '
         'with check-in and CSV export.'),
        ('Settings & Help',
         'Global plugin options, email templates, label translations, and '
         'a shortcode cheatsheet.'),
        ('License',
         'Where you paste your purchase code to unlock automatic updates '
         'and premium templates.'),
    ]))
    return s


def ch_settings():
    s = chapter_header(5, 'Plugin Settings')
    s.append(para(
        'Open <b>Events Pro -> Settings & Help</b>. The page is split into '
        'four panels on the left and a shortcode cheatsheet on the right. '
        'Click <b>Save Settings</b> at the bottom whenever you change anything.'
    ))
    s += section('Features Control')
    s.append(para('Five toggles that affect every event:'))
    s.append(bullet_list([
        '<b>Enable Time</b> - shows hours and minutes alongside the date.',
        '<b>Enable Location</b> - turns the location into a clickable Google '
        'Maps link.',
        '<b>Event Countdown</b> - displays the live "starts in" timer on '
        'upcoming events.',
        '<b>Add to Calendar</b> - shows the .ics download button.',
        '<b>Hide Past Events</b> - automatically hides finished events from '
        'the public listings.',
    ]))
    s += section('Venue Management')
    s.append(para(
        'Switch between Free Text and Predefined Venues at any time. If you '
        'choose Predefined, type one venue name per line in the textarea '
        'below. Examples: "Main Hall", "Workshop Room A", "Outdoor Arena".'
    ))
    s.append(warn_box(
        'When Predefined is selected, the plugin will <b>refuse to publish</b> '
        'an event if its venue is already booked at an overlapping time. '
        'You will see a clear red banner explaining which event conflicts.'
    ))
    s += section('Automated Emails')
    s.append(para(
        'Two email templates ship with the plugin: a confirmation email '
        '(sent immediately after registration) and a reminder email (sent '
        '24 hours before the event starts). Each one has a <b>Subject</b> '
        'and a <b>Body</b> field, and you can use these tags inside them:'
    ))
    s.append(key_value_table([
        ('{name}',       'The attendee\'s full name.'),
        ('{event_name}', 'The title of the event.'),
        ('{status}',     'Either CONFIRMED or WAITLIST.'),
        ('{event_date}', 'The event start date and time, formatted with '
                         'your WordPress locale settings.'),
    ]))
    s += section('Text & Translation')
    s.append(para(
        'Customize labels that appear on the public event pages, such as '
        '"Event Schedule", "Event Gallery", the "Join Waitlist" button '
        'text, and more. Useful if you want to use different wording (for '
        'example "Agenda" instead of "Schedule"), or to localize the plugin '
        'into another language without using a translation file.'
    ))
    s += section('Shortcodes Cheatsheet')
    s.append(para(
        'On the right side of the Settings page you will find every '
        'shortcode the plugin offers. Click any shortcode and it is copied '
        'to your clipboard. You can paste them into any page or post. '
        'Chapter 17 explains each one in detail.'
    ))
    return s


def ch_first_event():
    s = chapter_header(6, 'Creating Your First Event')
    s.append(para(
        'This is the chapter you will return to most often. Take it slow '
        'the first time - by the end, you will know how to fill in every '
        'field on an event.'
    ))
    s += section('Open the editor')
    s.append(numbered_list([
        'In the sidebar click <b>Events Pro -> Add Event</b>.',
        'A standard WordPress editor opens. Type the event title at the top '
        '(for example, "Annual Tech Conference 2026").',
        'Use the main editor area to write a long description with images, '
        'embeds, headings - anything WordPress allows.',
    ]))
    s += section('The "Event Configuration & Details" box')
    s.append(para(
        'Below the editor you will find a configuration panel. This is '
        'where the magic happens. The fields are:'
    ))
    s.append(key_value_table([
        ('Start Date / End Date',
         'When the event begins and ends. If "Enable Time" is on, you can '
         'pick the hour and minute too. The end date is optional - leave '
         'it blank for events with no fixed ending.'),
        ('Event Status',
         'Calculated automatically from the start and end dates: Upcoming, '
         'Ongoing, or Finished. The only manual option is the "Cancel this '
         'event" checkbox, which sets the status to Cancelled.'),
        ('Total Capacity',
         'Maximum number of confirmed seats. Leave blank for unlimited. '
         'Once full, new registrations go to the waitlist.'),
        ('Calendar Color',
         'A color used to mark this event on the public calendar. Helpful '
         'when you have many overlapping events.'),
        ('Enable Registration / Tickets',
         'A master switch. If it is off, the public event page shows only '
         'the description with no signup form. Turn it on to choose between '
         'Free RSVP and Paid Tickets.'),
        ('Enable Recurring Event',
         'Turn this on for events that repeat (daily, weekly, monthly, '
         'yearly). See Chapter 11.'),
        ('Event Overview',
         'A short summary that appears in a highlighted box at the top of '
         'the event page, just under the date.'),
    ]))
    s += section('Save and preview')
    s.append(numbered_list([
        'Click <b>Publish</b> on the right.',
        'Click <b>View Event</b> to open the public page in a new tab.',
        'Verify the date, location, and registration form look right.',
    ]))
    s.append(Spacer(1, 4))
    s.append(success_box(
        'You can edit the event at any time. The status updates itself - '
        'an "Upcoming" event will become "Ongoing" automatically when its '
        'start time arrives, and "Finished" after the end time.'
    ))
    return s


def ch_media():
    s = chapter_header(7, 'Event Media: Banner, Gallery & Video')
    s.append(para(
        'Strong visuals are what convert visitors into attendees. Each event '
        'has a dedicated <b>Media</b> box right under the configuration '
        'panel.'
    ))
    s += section('Featured Image')
    s.append(para(
        'Use the standard WordPress <b>Featured Image</b> on the right '
        'sidebar. This is the default banner for the event page and the '
        'image used in card listings.'
    ))
    s += section('Custom Banner URL (optional)')
    s.append(para(
        'If you want a different image specifically as the page hero (full-'
        'width banner at the top), paste its URL into <b>Custom Banner URL</b>. '
        'When set, this overrides the Featured Image only on the hero - '
        'card listings still use the Featured Image.'
    ))
    s += section('Video URL')
    s.append(para(
        'Paste any embeddable video link (YouTube, Vimeo, etc.). It will '
        'show up as a responsive video block on the event page, under the '
        'description.'
    ))
    s += section('Gallery')
    s.append(numbered_list([
        'Click <b>Manage Images</b> in the Gallery box.',
        'The WordPress media library opens. Select multiple images.',
        'Click <b>Choose</b> to attach them.',
        'You can remove an image from the gallery by clicking the small '
        'red X above its thumbnail.',
    ]))
    s.append(Spacer(1, 4))
    s.append(tip_box(
        'For the smoothest layout, upload images that are at least 1200px '
        'wide. The gallery will automatically resize them to a clean grid '
        'on the event page.'
    ))
    return s


def ch_location():
    s = chapter_header(8, 'Event Location & Venue Conflict Prevention')
    s.append(para(
        'How you enter a location depends on the global "Location Type" '
        'setting (Settings & Help -> Venue Management).'
    ))
    s += section('Free Text mode')
    s.append(para(
        'You see a single text field labelled "Address". Type whatever you '
        'want - "Main Hall, 12th floor", a full street address, "Online '
        'via Zoom", or anything in between. If "Enable Location" is on, the '
        'address becomes a clickable Google Maps link on the public page.'
    ))
    s += section('Predefined Venues mode')
    s.append(para(
        'You see a dropdown listing every venue from the Settings page. '
        'Pick one. The plugin will then check whether that venue is already '
        'booked for an overlapping time period.'
    ))
    s += section('What happens if there is a conflict?')
    s.append(numbered_list([
        'You hit Publish (or Update).',
        'The plugin checks every other published or scheduled event using '
        'the same venue.',
        'If their start/end times overlap with yours, the save is cancelled.',
        'You see a red admin notice: <i>"Venue Conflict! [venue] is already '
        'booked for [event] during this time. The event was NOT published."</i>',
        'Either change the time, change the venue, or update the conflicting '
        'event first.',
    ]))
    s.append(Spacer(1, 4))
    s.append(note_box(
        'Drafts are not affected by the conflict check. You can keep drafts '
        'with overlapping venues and resolve them before publishing.'
    ))
    return s


def ch_taxonomies():
    s = chapter_header(9, 'Categories, Types & Tags')
    s.append(para(
        'Three classification systems help your visitors find the right '
        'event. They behave like the standard WordPress Categories and '
        'Tags, just dedicated to events.'
    ))
    s.append(Spacer(1, 6))
    s.append(key_value_table([
        ('Event Categories',
         'Hierarchical (you can have parent / child). Best for broad '
         'themes: "Conferences", "Workshops", "Webinars".'),
        ('Event Types',
         'Also hierarchical. Best for format or audience: "Online", '
         '"In-person", "Members only".'),
        ('Event Tags',
         'Flat list. Best for free-form keywords: "AI", "marketing", '
         '"beginners".'),
    ]))
    s += section('How to assign them')
    s.append(numbered_list([
        'Open or create an event.',
        'On the right sidebar, look for <b>Event Categories</b>, '
        '<b>Event Types</b>, and <b>Event Tags</b> boxes.',
        'Tick existing terms or click "Add new" to create one on the fly.',
        'Save the event.',
    ]))
    s += section('How to use them publicly')
    s.append(bullet_list([
        'The advanced filter shortcode lets visitors filter by Category.',
        'The calendar shortcode accepts a category attribute, so you can '
        'embed multiple calendars - one per category - on different pages.',
        'WordPress automatically creates archive pages like '
        '<i>/event-category/conferences/</i>.',
    ]))
    return s


def ch_subevents():
    s = chapter_header(10, 'Sub-Events (Sessions and Workshops)')
    s.append(para(
        'A <b>sub-event</b> is a smaller event that belongs to a parent. '
        'Use sub-events for the talks inside a conference, the lessons '
        'inside a course, or the workshops inside a festival.'
    ))
    s += section('Why use sub-events?')
    s.append(bullet_list([
        'Each sub-event has its own page with its own date, speakers, '
        'description, and gallery.',
        'They appear as a chronological schedule on the parent event page.',
        'Visitors can register specifically for a session instead of the '
        'whole conference (if you allow it).',
        'Sub-events also show up on the calendar, marked with a small dot '
        'and using the parent event\'s color.',
    ]))
    s += section('How to create one')
    s.append(numbered_list([
        'In the sidebar click <b>Events Pro -> Sub Events -> Add New</b>.',
        'Fill in the title, description, dates, location, media - exactly '
        'like a main event.',
        'In the right sidebar, find the <b>Parent Event Connection</b> '
        'box and select the main event this session belongs to.',
        'Publish.',
    ]))
    s += section('How they appear publicly')
    s.append(para(
        'On the parent event page, all sub-events show up under the title '
        '"Event Schedule" (you can rename this label in Settings). They '
        'are sorted by date, with a colored date badge on the left and a '
        'short excerpt on the right.'
    ))
    return s


def ch_recurring():
    s = chapter_header(11, 'Recurring Events')
    s.append(para(
        'A recurring event is one that happens on a regular schedule, '
        'like a weekly meetup or a monthly seminar. Instead of creating '
        'twelve separate events for a year of monthly seminars, you create '
        'one event and turn on recurrence.'
    ))
    s += section('How to set it up')
    s.append(numbered_list([
        'Open or create an event.',
        'In the configuration panel, tick <b>Enable Recurring Event</b>.',
        'Pick the recurrence: <b>Daily</b>, <b>Weekly</b>, <b>Monthly</b>, '
        'or <b>Yearly</b>.',
        'Save.',
    ]))
    s += section('How recurring events appear')
    s.append(bullet_list([
        'On the public calendar, the same event is automatically painted '
        'on every matching date in the visible month - even months in the '
        'future.',
        'Each occurrence keeps the original duration. If your weekly event '
        'is two hours long, every weekly copy is two hours long.',
        'Clicking any copy opens the same single event page.',
    ]))
    s.append(Spacer(1, 4))
    s.append(note_box(
        'For safety, recurrence is capped at <b>five years</b> from the start '
        'date. This prevents a typo (like a daily event that should have been '
        'monthly) from filling the calendar forever.'
    ))
    return s


def ch_rsvp():
    s = chapter_header(12, 'Free RSVP & the Smart Waitlist')
    s.append(para(
        'Free RSVP is the simplest way to collect registrations. Visitors '
        'fill in a name, email and (optional) phone number, and receive an '
        'instant confirmation email with a QR ticket. No payment, no setup '
        'on external services.'
    ))
    s += section('Turning it on')
    s.append(numbered_list([
        'Open or create an event.',
        'In the configuration panel, tick <b>Enable Registration / '
        'Tickets</b>.',
        'For <b>Registration Type</b>, leave it on <b>Free RSVP & '
        'Waitlist</b> (the default).',
        'Optionally set a <b>Total Capacity</b>. Leave blank for unlimited.',
        'Save.',
    ]))
    s += section('How visitors register')
    s.append(numbered_list([
        'They open the event page.',
        'They fill in the registration form.',
        'They click <b>Confirm Registration</b>.',
        'A success message appears, and they receive a confirmation email '
        'with a QR ticket within a few seconds.',
    ]))
    s += section('What happens when the event is full?')
    s.append(para(
        'If you set a capacity and it is reached, the form changes:'
    ))
    s.append(bullet_list([
        'A yellow banner says "Event is Fully Booked! You can still join '
        'the Waitlist."',
        'The submit button label becomes "Join Waitlist" (you can rename '
        'this in Settings).',
        'New registrations are saved with status <b>Waitlist</b>. They do '
        '<i>not</i> count against capacity, and they do <i>not</i> get a QR '
        'ticket yet.',
        'The waitlisted attendee receives a different email confirming they '
        'are on the list.',
    ]))
    s += section('Automatic promotion from the waitlist')
    s.append(para('A waitlisted person is automatically promoted to '
                  'confirmed when:'))
    s.append(bullet_list([
        'You delete a confirmed attendee from the Attendees page.',
        'You manually mark a confirmed attendee as deleted in bulk.',
        'You raise the event\'s capacity to a number higher than the '
        'current confirmed count.',
    ]))
    s.append(para(
        'When this happens, the oldest waitlisted person is promoted, '
        'their status becomes Confirmed, a fresh QR ticket is generated, '
        'and they receive a "Good news, a seat opened up" email.'
    ))
    s.append(Spacer(1, 4))
    s.append(success_box(
        'You can let waitlisting do the boring part of event management for '
        'you. Set a realistic capacity, accept everyone who registers (some '
        'will drop out), and the system fills empty seats from the waitlist '
        'automatically.'
    ))
    return s


def ch_paid_tickets():
    s = chapter_header(13, 'Paid Tickets with WooCommerce')
    s.append(para(
        'For paid events, Core Events Pro integrates with WooCommerce - the '
        'most popular WordPress e-commerce plugin. You define ticket tiers '
        '(VIP, Standard, Early Bird, etc.), the plugin creates matching '
        'WooCommerce products automatically, and buyers go through the '
        'normal WooCommerce checkout.'
    ))
    s += section('Before you start')
    s.append(bullet_list([
        'Install and activate WooCommerce.',
        'Configure at least one payment method in WooCommerce (Stripe, '
        'PayPal, bank transfer, cash on delivery, etc.).',
        'Set your store currency in WooCommerce -> Settings.',
    ]))
    s += section('Switching an event to paid tickets')
    s.append(numbered_list([
        'Open or create an event.',
        'Tick <b>Enable Registration / Tickets</b>.',
        'For <b>Registration Type</b>, choose <b>Paid Tickets '
        '(WooCommerce)</b>.',
        'A new section called <b>Tickets & Pricing (WooCommerce)</b> '
        'appears.',
    ]))
    s += section('Adding ticket tiers')
    s.append(numbered_list([
        'Click <b>+ Add Ticket Tier</b>.',
        'Type the ticket name (e.g. "VIP").',
        'Type the price.',
        'Optionally type the capacity for that specific tier (leave blank '
        'for unlimited).',
        'Repeat for each tier.',
        'Save the event.',
    ]))
    s.append(Spacer(1, 4))
    s.append(success_box(
        'On save, the plugin creates one hidden WooCommerce product per '
        'ticket. The products are virtual (no shipping needed) and hidden '
        'from the shop catalog so they only appear on the event page.'
    ))
    s += section('What buyers see')
    s.append(para(
        'On the public event page, each tier becomes a row with a price '
        'and a <b>Buy Ticket</b> button. Clicking the button adds the '
        'product to the cart and forwards the buyer to checkout. After '
        'payment, the plugin automatically:'
    ))
    s.append(bullet_list([
        'Creates one attendee record per ticket purchased (so quantity 3 '
        'creates 3 attendees).',
        'Generates a unique QR code for each ticket.',
        'Sends one email to the buyer containing all their QR tickets.',
        'Marks every attendee as Confirmed.',
    ]))
    s += section('Editing a ticket later')
    s.append(para(
        'Just change the price or capacity inside the event editor and '
        'save. The matching WooCommerce product updates itself. Click '
        '<b>Edit in WC</b> next to a ticket if you ever need to fine-tune '
        'the WooCommerce product directly (tax classes, downloadable files, '
        'etc.).'
    ))
    s.append(warn_box(
        'If you remove a ticket tier from the event editor, its '
        'WooCommerce product is <b>not</b> automatically deleted. This is '
        'on purpose - you may have past sales linked to it. Delete the '
        'product manually from <b>Products -> All Products</b> if you no '
        'longer need it.'
    ))
    return s


def ch_qr():
    s = chapter_header(14, 'QR Code Tickets & Door Check-in')
    s.append(para(
        'Every confirmed attendee - whether free or paid - receives a QR '
        'code in their confirmation email. At the venue, you scan the code '
        'with any smartphone camera and the attendee is checked in '
        'instantly.'
    ))
    s += section('How tickets are generated')
    s.append(bullet_list([
        'When someone registers (or pays), the plugin creates a unique, '
        'random token for that attendee.',
        'A QR image is generated from the token and embedded in the email.',
        'The same email also contains a fallback URL the attendee can open '
        'directly if they cannot scan their own QR code.',
    ]))
    s += section('How to scan tickets at the door')
    s.append(numbered_list([
        'Make sure the device used to scan is logged in to the WordPress '
        'site as an Administrator or Editor (this is the security check).',
        'Open the camera app on a smartphone or tablet.',
        'Point it at the QR code on the attendee\'s phone or printout.',
        'Tap the link that appears.',
        'A page loads with a clear status: <b>SUCCESS</b> in green, '
        '<b>ALREADY CHECKED IN</b> in yellow, or <b>INVALID TICKET</b> in '
        'red.',
    ]))
    s.append(Spacer(1, 4))
    s.append(tip_box(
        'For a busy door, dedicate one staff member with their own logged-in '
        'tablet. Bookmark the homepage and just keep tapping the camera. '
        'Each scan takes about two seconds.'
    ))
    s += section('Manual check-in (no scanner)')
    s.append(para(
        'If a ticket cannot be scanned (lost phone, missed email), you can '
        'check the attendee in manually:'
    ))
    s.append(numbered_list([
        'Open <b>Events Pro -> Attendees</b>.',
        'Filter by the event in the dropdown.',
        'Find the person by name or email.',
        'Click <b>Mark Attended</b> next to their row.',
    ]))
    s += section('Anti-cheat protection')
    s.append(bullet_list([
        'Each QR code can only be used once. The second scan shows '
        '"ALREADY CHECKED IN" - you will know if a ticket was duplicated.',
        'Only logged-in staff can scan. A guest cannot trick the system by '
        'opening the QR link themselves.',
        'Tokens are 32 random characters, so they cannot be guessed.',
    ]))
    return s


def ch_attendees():
    s = chapter_header(15, 'Managing Attendees')
    s.append(para(
        'The Attendees page is your single source of truth for everyone '
        'registered to anything. Open it from <b>Events Pro -> Attendees</b>.'
    ))
    s += section('What you can see')
    s.append(bullet_list([
        '<b>Name</b> - the attendee\'s name (with a Delete link on hover).',
        '<b>Email</b> - clickable mailto link.',
        '<b>Phone</b> - if they provided one.',
        '<b>Event</b> - which event or session they signed up for.',
        '<b>Status</b> - Confirmed (green) or Waitlist (orange).',
        '<b>Attended</b> - check-in button or a green "Yes".',
        '<b>Registration Date</b> - when they signed up.',
    ]))
    s += section('Filtering and searching')
    s.append(numbered_list([
        'Use the dropdown at the top to filter by a specific event.',
        'Click any column header to sort.',
        'Use the standard pagination at the bottom to page through.',
    ]))
    s += section('Bulk actions')
    s.append(para(
        'Tick the checkbox at the top of the list to select all rows on '
        'the current page, then choose an action from the dropdown:'
    ))
    s.append(bullet_list([
        '<b>Mark as Attended</b> - useful for marking a whole batch after '
        'a manual paper check-in.',
        '<b>Mark as Unattended</b> - undoes the previous action.',
        '<b>Delete</b> - removes the attendees. If they were Confirmed and '
        'the event has a waitlist, the next people in line are promoted '
        'automatically.',
    ]))
    s += section('Exporting to CSV')
    s.append(numbered_list([
        'Filter by the event you want to export.',
        'Click <b>Export This List (CSV)</b> at the top of the page.',
        'A file with all attendees for that event downloads instantly.',
    ]))
    s.append(para(
        'You can also export a single event from inside the event editor. '
        'In the Attendees List box, click <b>Export CSV</b>.'
    ))
    s.append(success_box(
        'The CSV is ready to import into Mailchimp, Excel, Google Sheets, '
        'or any CRM. Columns: Name, Email, Phone, Status, Attended, '
        'Registration Date.'
    ))
    return s


def ch_bulk_tools():
    s = chapter_header(16, 'Bulk Tools: Clone, Bulk Edit, CSV Import')
    s += section('Cloning an event')
    s.append(para(
        'To create a copy of an existing event with all its settings, media, '
        'taxonomies and ticket tiers:'
    ))
    s.append(numbered_list([
        'Open <b>Events Pro -> All Main Events</b>.',
        'Hover over the event you want to copy.',
        'Click the blue <b>Clone Event</b> link in the row actions.',
        'You are taken straight to the editor of the new copy, which is '
        'saved as a Draft and titled "[Original title] (Copy)".',
        'Change the date (and anything else), then publish.',
    ]))
    s.append(tip_box(
        'For event series - same workshop every Saturday, for example - '
        'cloning is faster than creating from scratch and avoids missing '
        'fields.'
    ))
    s += section('Bulk status actions')
    s.append(numbered_list([
        'Open <b>All Main Events</b>.',
        'Tick the checkbox next to each event you want to update.',
        'From the <b>Bulk Actions</b> dropdown choose <b>Mark as Finished</b> '
        'or <b>Mark as Cancelled</b>.',
        'Click <b>Apply</b>.',
    ]))
    s += section('Importing events from a CSV file')
    s.append(para(
        'If you already have your event list in a spreadsheet, you can '
        'import them in one go.'
    ))
    s.append(subsection('CSV format'))
    s.append(para(
        'The plugin expects six columns, in this exact order:'
    ))
    s.append(key_value_table([
        ('1. Title',       'The event title.'),
        ('2. Content',     'The full description (HTML allowed).'),
        ('3. Start Date',  'YYYY-MM-DDTHH:MM (e.g. 2026-05-01T18:30).'),
        ('4. End Date',    'Same format. Leave empty if none.'),
        ('5. Capacity',    'A number, or 0 for unlimited.'),
        ('6. Location',    'Free text address.'),
    ]))
    s.append(para('Save your file as CSV (comma-separated values) with '
                  'UTF-8 encoding.'))
    s.append(subsection('How to import'))
    s.append(numbered_list([
        'Open <b>Events Pro -> Dashboard</b>.',
        'On the right, find the <b>Import Events (CSV)</b> box.',
        'Click <b>Choose File</b> and pick your CSV.',
        'Click <b>Upload & Import</b>.',
        'A green banner confirms how many events were imported.',
    ]))
    s.append(note_box(
        'Imported events are published immediately with status Upcoming and '
        'a default blue calendar color. Edit them afterwards to add media, '
        'tickets, or change the color.'
    ))
    return s


def ch_shortcodes():
    s = chapter_header(17, 'Displaying Events on Your Website')
    s.append(para(
        'A shortcode is a small piece of text in square brackets that you '
        'paste into any WordPress page, post, or widget. Core Events Pro '
        'ships with seven shortcodes, each producing a different layout. '
        'Mix and match them on your homepage, event landing page, sidebar, '
        'or footer.'
    ))
    s += section('All available shortcodes')
    s.append(shortcode_box(
        '[event_calendar]',
        'A full interactive monthly calendar. Visitors can browse '
        'previous / next months, click a day to see what is happening, and '
        'jump to event pages.'
    ))
    s.append(Spacer(1, 4))
    s.append(shortcode_box(
        '[events_advanced_filter]',
        'A search box plus category and status dropdowns. Results update '
        'live with no page reload. Best for a "What\'s on" page.'
    ))
    s.append(Spacer(1, 4))
    s.append(shortcode_box(
        '[next_event]',
        'A single hero card for the next upcoming event - large title, '
        'date, and a Join button. Great for homepages.'
    ))
    s.append(Spacer(1, 4))
    s.append(shortcode_box(
        '[events_grouped]',
        'Two columns side by side: Upcoming events on the left, Past '
        'events on the right. If "Hide Past Events" is on in Settings, '
        'only Upcoming is shown.'
    ))
    s.append(Spacer(1, 4))
    s.append(shortcode_box(
        '[events_list status="upcoming" limit="6" col="3"]',
        'A grid of event cards. <b>status</b> can be upcoming, ongoing or '
        'finished; <b>limit</b> sets how many to show; <b>col</b> sets the '
        'number of columns (1 to 4).'
    ))
    s.append(Spacer(1, 4))
    s.append(shortcode_box(
        '[main_event id="123"]',
        'Embeds a specific event card by ID anywhere on the site (e.g. '
        'inside a blog post about that event).'
    ))
    s.append(Spacer(1, 4))
    s.append(shortcode_box(
        '[sub_events main_event_id="123"]',
        'Lists every sub-event linked to a given parent. Useful when you '
        'want to show the schedule on a custom page.'
    ))
    s += section('Where to use them')
    s.append(numbered_list([
        'Edit any page or post in WordPress.',
        'Click the + (Insert Block) and choose <b>Shortcode</b> (or simply '
        'paste in a Paragraph block).',
        'Paste the shortcode text exactly as shown (you can copy it with '
        'one click from <b>Settings & Help</b>).',
        'Update the page and preview it on the front-end.',
    ]))
    s.append(tip_box(
        'You can use the same shortcode multiple times on the same page '
        'with different attributes. For example, two grids - one of '
        'upcoming events, one of finished ones - just stack two '
        '[events_list] shortcodes with different status values.'
    ))
    return s


def ch_emails():
    s = chapter_header(18, 'Email Notifications')
    s.append(para(
        'The plugin sends three kinds of emails automatically. All of '
        'them go through standard WordPress mail, so they are delivered by '
        'whatever SMTP plugin or transactional service you have already '
        'configured.'
    ))
    s.append(Spacer(1, 4))
    s.append(key_value_table([
        ('Confirmation email',
         'Sent immediately after a successful free RSVP. Contains the QR '
         'ticket if the attendee is Confirmed, or a "you are on the '
         'waitlist" note if not.'),
        ('Waitlist promotion email',
         'Sent automatically when a waitlisted attendee gets promoted to '
         'Confirmed (because someone cancelled or capacity grew). Includes '
         'a fresh QR ticket.'),
        ('24-hour reminder',
         'Sent once, in the hour 24 hours before the event starts, to all '
         'Confirmed attendees. Run by the hourly background task.'),
        ('WooCommerce ticket email',
         'Sent after a paid ticket order is completed. Contains one QR '
         'code per ticket purchased, grouped by event.'),
    ]))
    s += section('Customizing the templates')
    s.append(numbered_list([
        'Open <b>Events Pro -> Settings & Help</b>.',
        'Scroll to <b>Automated Emails</b>.',
        'Edit the subject line and body for each template.',
        'Use the dynamic tags inside your text (see Chapter 5).',
        'Click <b>Save Settings</b> at the bottom of the page.',
    ]))
    s.append(warn_box(
        'WordPress\'s default mail can land in spam. For a professional '
        'experience, install an SMTP plugin like FluentSMTP or WP Mail '
        'SMTP and connect it to a transactional service such as Postmark, '
        'SendGrid, or Amazon SES.'
    ))
    return s


def ch_license():
    s = chapter_header(19, 'Activating Your License')
    s.append(para(
        'Your purchase came with a unique license key. Activating it on '
        'your site unlocks automatic updates, premium templates, and '
        'advanced modules.'
    ))
    s += section('How to activate')
    s.append(numbered_list([
        'Open <b>Events Pro -> License</b>.',
        'Paste your purchase code in the <b>Purchase Code</b> field.',
        'Click <b>Activate License</b>.',
        'A green "Active" status appears, along with the domain the '
        'license is now bound to.',
    ]))
    s += section('Moving the license to another site')
    s.append(numbered_list([
        'On the old site, open the License page and click <b>Deactivate '
        'License</b>.',
        'On the new site, install the plugin, then activate the same code.',
    ]))
    s.append(note_box(
        'Each license is bound to one domain at a time. If you forget to '
        'deactivate before moving, premium features will simply switch off '
        'on the old site - your event data is never lost.'
    ))
    return s


def ch_best_practices():
    s = chapter_header(20, 'Tips, Best Practices & Workflows')
    s += section('Plan your taxonomies before you launch')
    s.append(para(
        'Before adding many events, sketch the categories, types and tags '
        'you will need. Renaming or merging them later is fine, but doing '
        'it once at the start saves a lot of cleanup.'
    ))
    s += section('Use predefined venues from day one')
    s.append(para(
        'Even if you only have two rooms, switching to predefined venues '
        'is worth it: the conflict checker prevents double-booking '
        'mistakes, and you get cleaner reporting later.'
    ))
    s += section('Customize your emails before going public')
    s.append(para(
        'The default email templates are functional but plain. Spend ten '
        'minutes adding your brand voice, a short welcome message, and a '
        'support email signature. It changes the perceived quality of '
        'your event a lot.'
    ))
    s += section('Always test the QR scan flow first')
    s.append(para(
        'Create a test event, register yourself with a real email, '
        'receive the QR ticket, and scan it with the device you plan to '
        'use at the door. Five minutes of testing now beats a panic at '
        'the entrance later.'
    ))
    s += section('Use cloning for event series')
    s.append(para(
        'For events that repeat with small differences each time (a guest '
        'speaker, a different room) - clone the previous event and just '
        'change the date and title. Faster and safer than recreating '
        'from scratch.'
    ))
    s += section('Use the calendar as a homepage hero')
    s.append(para(
        'A common pattern: place [next_event] at the very top of your '
        'homepage, then [event_calendar] below it. Visitors see what is '
        'next at a glance and can browse everything else from the same '
        'page.'
    ))
    s += section('Set realistic capacities and trust the waitlist')
    s.append(para(
        'For free events, expect 20-30% no-show rates. Set capacity '
        'slightly above your room\'s real limit, then let the waitlist '
        'and auto-promotion fill empty seats automatically as cancellations '
        'come in.'
    ))
    return s


def ch_faq():
    s = chapter_header(21, 'Troubleshooting & FAQ')
    s += section('Why can I not save my event?')
    s.append(para(
        'The most common cause is a venue conflict. If you are using '
        'predefined venues and another event is already booked at the '
        'same time, the publish action is cancelled with a red banner. '
        'Either change the time, change the venue, or fix the other '
        'event first.'
    ))
    s += section('I enabled tickets but no form appears on the public page.')
    s.append(bullet_list([
        'Make sure the event is <b>Published</b>, not Draft.',
        'Open the event and confirm <b>Enable Registration / Tickets</b> '
        'is ticked.',
        'For paid tickets, ensure the registration type is set to '
        '<b>Paid Tickets (WooCommerce)</b> and at least one tier exists.',
        'For paid tickets, WooCommerce must be active.',
        'If the event status is Finished or Cancelled, registration is '
        'automatically closed by design.',
    ]))
    s += section('My attendees are not receiving the confirmation email.')
    s.append(bullet_list([
        'Ask them to check their spam folder.',
        'Use a free SMTP plugin (FluentSMTP / WP Mail SMTP) to bypass '
        'shared-hosting mail limits.',
        'Test by registering yourself with a Gmail address - if Gmail '
        'gets the mail but Outlook does not, the cause is your sending '
        'reputation, not the plugin.',
    ]))
    s += section('The calendar is empty / "Loading..." forever.')
    s.append(bullet_list([
        'Make sure at least one event has a Start Date.',
        'Open your browser console - if you see a 403 from the REST API, '
        'a security plugin or firewall is blocking the request. Whitelist '
        '<i>/wp-json/events/v1/</i>.',
        'Make sure the page where you placed the shortcode is published, '
        'not in preview mode.',
    ]))
    s += section('Reminder emails are not going out.')
    s.append(para(
        'The reminder runs on WordPress\'s built-in scheduler, which only '
        'fires when someone visits your site. On low-traffic sites, set '
        'up a real cron job from your hosting panel to ping <i>wp-cron.php</i> '
        'every 15 minutes - this guarantees reminders are sent on time.'
    ))
    s += section('A waitlisted person was not promoted when I deleted '
                 'someone confirmed.')
    s.append(bullet_list([
        'Verify the event has a non-zero capacity. With unlimited capacity, '
        'there is no waitlist to promote from.',
        'Verify there are people with status Waitlist for that specific '
        'event in the Attendees page.',
        'Promotion runs the moment you delete the confirmed attendee. If '
        'it did not work, refresh the page - the promoted person should '
        'now be Confirmed and have received the "good news" email.',
    ]))
    s += section('I want to refund a paid ticket.')
    s.append(para(
        'Refunds happen entirely inside WooCommerce. Open <b>WooCommerce -> '
        'Orders</b>, find the order, and process the refund as you would '
        'for any product. After the refund, manually remove the related '
        'attendee record from <b>Events Pro -> Attendees</b> if you do not '
        'want them to count anymore.'
    ))
    return s


def ch_glossary():
    s = chapter_header(22, 'Glossary')
    s.append(key_value_table([
        ('Main Event',
         'A top-level event with its own page, dates and registration form.'),
        ('Sub-Event',
         'A child of a main event. Used for sessions, talks, workshops.'),
        ('RSVP',
         '"Please respond" - a free registration where attendees confirm '
         'they will come.'),
        ('Waitlist',
         'A queue of people who registered after the event was full. They '
         'get promoted to Confirmed when a seat opens.'),
        ('Capacity',
         'The maximum number of Confirmed attendees. Waitlisted people do '
         'not count.'),
        ('Confirmed',
         'An attendee status meaning they have a seat and a QR ticket.'),
        ('Check-in',
         'Marking an attendee as physically present, usually by scanning '
         'their QR code at the door.'),
        ('Recurring event',
         'A single event that automatically repeats on the calendar at '
         'a chosen frequency.'),
        ('Shortcode',
         'A piece of text like [event_calendar] that you paste into a '
         'WordPress page to insert dynamic content.'),
        ('Taxonomy',
         'A way to classify items. The plugin offers three: Categories, '
         'Types, and Tags.'),
        ('Featured image',
         'The default banner WordPress uses for a post or event.'),
        ('License key',
         'Your unique purchase code. Activated on the License page to '
         'unlock premium features.'),
    ]))
    s.append(Spacer(1, 18))
    s.append(hr(width=17 * cm, color=BORDER, thickness=0.6))
    s.append(Spacer(1, 12))
    s.append(Paragraph(
        '<b>You are ready to run great events.</b>',
        ParagraphStyle('end', parent=styles['Body'], alignment=TA_CENTER,
                       fontSize=13, leading=18, textColor=DARK,
                       fontName='Helvetica-Bold')
    ))
    s.append(Spacer(1, 6))
    s.append(Paragraph(
        'Thank you for choosing Core Events Pro. For support requests, '
        'feature ideas, or to share what you have built, contact your '
        'plugin provider.',
        ParagraphStyle('endsub', parent=styles['Body'], alignment=TA_CENTER,
                       fontSize=10, leading=14, textColor=MUTED)
    ))
    return s


# ---------------------------------------------------------------------------
# Final assembly
# ---------------------------------------------------------------------------
def main(out_path='Core-Events-Pro-User-Guide.pdf'):
    doc = build_doc(out_path)
    story = []

    # Cover
    story.extend(cover_content())
    story.append(NextPageTemplate('Body'))
    story.append(PageBreak())

    # TOC
    story.extend(toc_content())
    story.append(PageBreak())

    # Chapters
    chapter_builders = [
        ch_introduction,
        ch_install,
        ch_wizard,
        ch_admin_tour,
        ch_settings,
        ch_first_event,
        ch_media,
        ch_location,
        ch_taxonomies,
        ch_subevents,
        ch_recurring,
        ch_rsvp,
        ch_paid_tickets,
        ch_qr,
        ch_attendees,
        ch_bulk_tools,
        ch_shortcodes,
        ch_emails,
        ch_license,
        ch_best_practices,
        ch_faq,
        ch_glossary,
    ]
    for i, builder in enumerate(chapter_builders):
        story.extend(builder())
        if i < len(chapter_builders) - 1:
            story.append(PageBreak())

    doc.build(story)
    print(f'PDF generated: {out_path}')


if __name__ == '__main__':
    main()
