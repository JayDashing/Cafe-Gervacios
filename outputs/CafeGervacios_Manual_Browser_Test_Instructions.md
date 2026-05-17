# Cafe Gervacios Manual Browser Testing Instructions

Source: `Test_Case-1_Real_Updated.csv`

Included only rows with `STATUS = PASSED`. No application code was changed. Use `http://127.0.0.1:8000` as the base URL. Admin and Staff accounts log in through `/admin/login`. Customer tests use public pages without logging in.

Automation note: the browser is used to set up and verify automation cases. The actual automation trigger is the Laravel scheduler/automation tick, not a visible browser button.

TEST CASE ID: WTL-001
MODULE: Waitlist Management
ROLE: Staff
PAGE/URL: /staff/queue
STEPS:
1. Login as staff from `/admin/login`.
2. Open `/staff/queue`.
3. Type the guest details in Name, Phone, and Party size.
4. Set Priority to Standard.
5. Click "Add to queue".

SAMPLE DATA:
- Name: Juan Dela Cruz
- Phone: 09171230001
- Party Size: 4
- Priority: Standard

TRIGGER ACTION:
Click "Add to queue".

EXPECTED RESULT:
A success toast appears and a queue number is assigned.

HOW TO VERIFY:
Open `/admin/waitlist` or the Waitlist panel on `/admin/tables` and confirm Juan Dela Cruz appears with the correct party size and queue number.

TEST CASE ID: WTL-002
MODULE: Waitlist Management
ROLE: Staff
PAGE/URL: /staff/queue
STEPS:
1. Login as staff from `/admin/login`.
2. Open `/staff/queue`.
3. Type the guest name and party size.
4. Leave Phone blank.
5. Set Priority to Standard.
6. Click "Add to queue".

SAMPLE DATA:
- Name: Maria Santos
- Phone:
- Party Size: 2
- Priority: Standard

TRIGGER ACTION:
Click "Add to queue" with the Phone field empty.

EXPECTED RESULT:
The system accepts the walk-in and shows a success toast.

HOW TO VERIFY:
Open `/admin/waitlist` and confirm Maria Santos appears even though no phone number was entered.

TEST CASE ID: WTL-003
MODULE: Waitlist Management
ROLE: Staff
PAGE/URL: /staff/queue
STEPS:
1. Login as staff.
2. Open `/staff/queue`.
3. Type an invalid name containing numbers or symbols.
4. Fill Phone and Party size with valid values.
5. Click "Add to queue".

SAMPLE DATA:
- Name: Juan123
- Phone: 09171230003
- Party Size: 3
- Priority: Standard

TRIGGER ACTION:
Click "Add to queue" after entering the invalid name.

EXPECTED RESULT:
A validation message appears under Name. The guest is not added to the queue.

HOW TO VERIFY:
Open `/admin/waitlist` and confirm no new row for Juan123 exists.

TEST CASE ID: WTL-004
MODULE: Waitlist Management
ROLE: Staff
PAGE/URL: /staff/queue
STEPS:
1. Login as staff.
2. Open `/staff/queue`.
3. First create an active entry using the sample phone number.
4. Without seating or removing that entry, create another walk-in using the same phone number.
5. Click "Add to queue".

SAMPLE DATA:
- Name: Duplicate Guest B
- Phone: 09171230004
- Party Size: 2
- First Entry Name: Duplicate Guest A
- Priority: Standard

TRIGGER ACTION:
Click "Add to queue" for the second entry using the duplicate phone number.

EXPECTED RESULT:
A duplicate warning appears saying the phone already has an active booking or queue entry.

HOW TO VERIFY:
Open `/admin/waitlist` and confirm only the first active entry for `09171230004` exists.

TEST CASE ID: WTL-005
MODULE: Waitlist Management
ROLE: Staff
PAGE/URL: /admin/waitlist
STEPS:
1. Login as staff or admin.
2. Create a waiting guest from `/staff/queue` if none exists.
3. Open `/admin/waitlist`.
4. Find the waiting guest row.
5. Click "SMS" on that row.

SAMPLE DATA:
- Name: SMS Ready Guest
- Phone: 09171230005
- Party Size: 2
- Priority: Standard

TRIGGER ACTION:
Click the "SMS" button on a waiting guest.

EXPECTED RESULT:
The guest moves to "Texted, on hold". A hold expiration and confirmation code are created, and the table-ready SMS is dispatched.

HOW TO VERIFY:
Check the "Texted, on hold" section, the table status on `/admin/tables`, and Semaphore/report or SMS logs for the sent message.

TEST CASE ID: WTL-006
MODULE: Waitlist Management
ROLE: Staff
PAGE/URL: /admin/waitlist
STEPS:
1. Login as staff or admin.
2. Use a notified guest from WTL-005, or click "SMS" for a waiting guest.
3. Read the 6-character hold confirmation code from the SMS received by the guest.
4. In "Texted, on hold", type the code in the "6-char code" field.
5. Choose a compatible table from the "Table" dropdown.
6. Click "Seat".

SAMPLE DATA:
- Name: Seat Correct Code
- Phone: 09171230006
- Party Size: 2
- Hold Code: Use the real 6-character code from the SMS

TRIGGER ACTION:
Click "Seat" after entering the correct hold confirmation code and choosing a table.

EXPECTED RESULT:
The guest is seated successfully. The queue entry leaves the hold list and the selected table becomes Occupied.

HOW TO VERIFY:
Open `/admin/tables` and confirm the selected table is Occupied. Open `/admin/waitlist` and confirm the guest is no longer waiting/on hold.

TEST CASE ID: WTL-007
MODULE: Waitlist Management
ROLE: Staff
PAGE/URL: /admin/waitlist
STEPS:
1. Login as staff or admin.
2. Create or use a notified guest in "Texted, on hold".
3. Type an incorrect code in the "6-char code" field.
4. Choose a compatible table.
5. Click "Seat".

SAMPLE DATA:
- Name: Wrong Code Guest
- Phone: 09171230007
- Party Size: 2
- Wrong Hold Code: 000000

TRIGGER ACTION:
Click "Seat" with the wrong confirmation code.

EXPECTED RESULT:
An incorrect confirmation code error appears. The guest is not seated.

HOW TO VERIFY:
Confirm the guest remains in "Texted, on hold" and the selected table status does not change to Occupied.

TEST CASE ID: WTL-008
MODULE: Waitlist Management
ROLE: Admin
PAGE/URL: /admin/waitlist
STEPS:
1. Login as admin.
2. Open `/admin/waitlist`.
3. Find the active or held waitlist guest.
4. Click "Remove".
5. Confirm the browser confirmation prompt.

SAMPLE DATA:
- Name: Cancel Hold Guest
- Phone: 09171230008
- Party Size: 2
- Priority: Standard

TRIGGER ACTION:
Click "Remove" for the waitlist entry.

EXPECTED RESULT:
The entry is cancelled/removed from the visible waitlist. If it had a held table, that table is released.

HOW TO VERIFY:
Refresh `/admin/waitlist` and confirm the guest is gone. Open `/admin/tables` and confirm the held table is Free.

TEST CASE ID: WTL-009
MODULE: Waitlist Management
ROLE: Staff
PAGE/URL: /admin/waitlist
STEPS:
1. Login as staff or admin.
2. Create or use a notified guest in "Texted, on hold".
3. Look at the "Until" time/countdown.
4. Click "+5m hold".

SAMPLE DATA:
- Name: Extend Hold Guest
- Phone: 09171230009
- Party Size: 2
- Priority: Standard

TRIGGER ACTION:
Click "+5m hold" for a notified guest.

EXPECTED RESULT:
The hold expiration extends by five minutes and the countdown increases.

HOW TO VERIFY:
Compare the previous "Until" time/countdown with the new one; it should be about five minutes later.

TEST CASE ID: FLM-001
MODULE: Floor Map Management
ROLE: Admin
PAGE/URL: /admin/seating-layout
STEPS:
1. Login as admin.
2. Open `/admin/tables`.
3. Click "Edit Layout", or open `/admin/seating-layout` directly.
4. Wait for the blueprint/floor plan to load.
5. Check that table cards, seat markers, and the status legend appear.

SAMPLE DATA:
- Name: N/A
- Phone: N/A
- Party Size: N/A
- Expected Existing Data: Saved tables/seats with labels, capacities, and statuses

TRIGGER ACTION:
Open the Seating layout page.

EXPECTED RESULT:
The floor map loads and displays the saved layout data.

HOW TO VERIFY:
Confirm visible table labels/capacities match the seeded/current floor map and that the status legend appears.

TEST CASE ID: FLM-002
MODULE: Floor Map Management
ROLE: Admin
PAGE/URL: /admin/seating-layout
STEPS:
1. Login as admin.
2. Open `/admin/seating-layout`.
3. Click "Add seats".
4. Click a valid empty spot inside the blueprint image.
5. In the modal, enter the table label and capacity.
6. Keep Seat / table type as Standard unless testing another type.
7. Click "Save", then "Close".

SAMPLE DATA:
- Name: N/A
- Phone: N/A
- Party Size: N/A
- Table Label: Manual T21
- Capacity: 4
- Seat / Table Type: Standard

TRIGGER ACTION:
Click "Save" after placing the marker inside the map.

EXPECTED RESULT:
A new table/seat marker is created on the floor map.

HOW TO VERIFY:
Confirm the new marker/card appears on `/admin/seating-layout` and also appears on `/admin/tables` after refresh.

TEST CASE ID: FLM-003
MODULE: Floor Map Management
ROLE: Admin
PAGE/URL: /admin/seating-layout
STEPS:
1. Login as admin.
2. Open `/admin/seating-layout`.
3. Click "Add seats".
4. Click outside the blueprint image area, such as the toolbar/blank page area instead of the map.
5. For strict API verification in the browser, use DevTools Network/Console to send an out-of-range placement request to `/admin/api/seats/place` with `pos_x` greater than 100 or `pos_y` less than 0.

SAMPLE DATA:
- Name: N/A
- Phone: N/A
- Party Size: N/A
- Invalid pos_x: 150
- Invalid pos_y: -5

TRIGGER ACTION:
Try to place a seat outside the valid map coordinate area.

EXPECTED RESULT:
The request is rejected or no placement modal/table is created. API validation returns coordinate errors for out-of-range values.

HOW TO VERIFY:
Confirm the table count and visible markers did not increase. If using Network/DevTools, confirm the validation response contains coordinate errors.

TEST CASE ID: FLM-004
MODULE: Floor Map Management
ROLE: Admin
PAGE/URL: /admin/seating-layout
STEPS:
1. Login as admin.
2. Open `/admin/seating-layout`.
3. Click an existing table marker/card.
4. Change "Name (table label)".
5. Change "Capacity (guests)" to a valid number not below the mapped seat count.
6. Click "Save", then "Close".

SAMPLE DATA:
- Name: N/A
- Phone: N/A
- Party Size: N/A
- Old Table Label: Manual T21
- New Table Label: Manual T21 Updated
- Capacity: 5

TRIGGER ACTION:
Click "Save" in the seat/table modal.

EXPECTED RESULT:
The table label and capacity are updated on the map.

HOW TO VERIFY:
Refresh `/admin/seating-layout` or open `/admin/tables` and confirm the updated label/capacity remains.

TEST CASE ID: FLM-005
MODULE: Floor Map Management
ROLE: Admin
PAGE/URL: /admin/seating-layout
STEPS:
1. Login as admin.
2. Open `/admin/seating-layout`.
3. Choose a grouped table with two or more visible seat markers.
4. Click a marker in that group.
5. Set "Capacity (guests)" lower than the number of dots in the group.
6. Click "Save".

SAMPLE DATA:
- Name: N/A
- Phone: N/A
- Party Size: N/A
- Grouped Table: Any 2+ marker table
- Invalid Capacity: 1

TRIGGER ACTION:
Click "Save" with capacity below the mapped seat count.

EXPECTED RESULT:
The system blocks the update and shows a capacity validation message.

HOW TO VERIFY:
Confirm the table capacity on the map remains unchanged after closing/reopening the modal.

TEST CASE ID: FLM-006
MODULE: Floor Map Management
ROLE: Admin
PAGE/URL: /admin/seating-layout
STEPS:
1. Login as admin.
2. Open `/admin/seating-layout`.
3. Create or identify at least two separate seat markers.
4. Click "Selection".
5. Select two or more markers by dragging a box around them or Ctrl-clicking them.
6. Click "Group as table".
7. Enter a label if desired.
8. Click "Merge tables".

SAMPLE DATA:
- Name: N/A
- Phone: N/A
- Party Size: N/A
- Group Label: Manual Merge 01
- Selected Markers: 2 or more

TRIGGER ACTION:
Click "Merge tables" in the "Merge into one table" modal.

EXPECTED RESULT:
The selected markers become one table group with a dashed/merged visual region and combined capacity.

HOW TO VERIFY:
Confirm the map shows one merged group/card and the original selected markers belong to the same table.

TEST CASE ID: FLM-007
MODULE: Floor Map Management
ROLE: Admin
PAGE/URL: /admin/seating-layout
STEPS:
1. Login as admin.
2. Open `/admin/seating-layout`.
3. Click one marker inside a multi-seat table/group.
4. Open "Remove from map".
5. Click "Remove this seat only".

SAMPLE DATA:
- Name: N/A
- Phone: N/A
- Party Size: N/A
- Target Table: Manual Merge 01 or any multi-seat table

TRIGGER ACTION:
Click "Remove this seat only".

EXPECTED RESULT:
Only the selected seat marker is removed. The remaining table/group stays on the map.

HOW TO VERIFY:
Confirm the selected dot disappears and the table capacity/seat count updates after refresh.

TEST CASE ID: FLM-008
MODULE: Floor Map Management
ROLE: Admin
PAGE/URL: /admin/seating-layout
STEPS:
1. Login as admin.
2. Open `/admin/seating-layout`.
3. Create or choose a temporary table with no bookings.
4. Click its marker/card.
5. Open "Remove from map".
6. Click "Remove whole table".

SAMPLE DATA:
- Name: N/A
- Phone: N/A
- Party Size: N/A
- Temporary Table Label: Manual Delete 01
- Capacity: 2

TRIGGER ACTION:
Click "Remove whole table".

EXPECTED RESULT:
The full table and all its markers are removed from the floor map.

HOW TO VERIFY:
Refresh `/admin/seating-layout` and confirm the table/markers no longer appear.

TEST CASE ID: FLM-009
MODULE: Floor Map Management
ROLE: Admin
PAGE/URL: /admin/seating-layout
STEPS:
1. Login as admin.
2. Create or identify a reservation that has an assigned table.
3. Open `/admin/seating-layout`.
4. Click that reserved/booked table.
5. Open "Remove from map".
6. Click "Remove whole table".

SAMPLE DATA:
- Name: Booked Delete Guard
- Phone: 09171230019
- Party Size: 2
- Target Table: Any table with an existing booking

TRIGGER ACTION:
Click "Remove whole table" for a table with bookings.

EXPECTED RESULT:
The system rejects deletion and shows that the table has bookings and cannot be removed.

HOW TO VERIFY:
Confirm the table remains visible on `/admin/seating-layout` and the booking remains assigned in `/admin/bookings`.

TEST CASE ID: FLM-010
MODULE: Floor Map Management
ROLE: Admin
PAGE/URL: /admin/tables
STEPS:
1. Login as admin.
2. Open `/admin/tables`.
3. Click a table on the Table Check map.
4. Confirm the quick action popover opens for table operations.
5. If a waitlist guest needs seating, use the Waitlist panel on the right and click "Seat" or choose a "Table" dropdown item.
6. Click a compatible table on the map when the waitlist seating flow is active.

SAMPLE DATA:
- Name: Map Mode Guest
- Phone: 09171230020
- Party Size: 2

TRIGGER ACTION:
Switch between normal table click behavior and waitlist seating behavior by using the map/table selection flow.

EXPECTED RESULT:
Normal clicks open table quick actions; waitlist seating actions target the table for guest seating instead.

HOW TO VERIFY:
Confirm the selected table gets the proper visual highlight and the expected action happens: quick action popover in table mode, guest seating/table assignment in waitlist mode.

TEST CASE ID: AUT-001
MODULE: Automation
ROLE: Admin
PAGE/URL: /admin/settings?modal=timing
STEPS:
1. Login as admin.
2. Open `/admin/settings?modal=devices` and make sure "Turn automation on" is checked.
3. Open `/admin/settings?modal=timing`, set "Hold (min)" to 1, then click "Save".
4. Create a waiting guest, then open `/admin/waitlist` and click "SMS" so the guest becomes "Texted, on hold".
5. Wait until the hold timer expires and the scheduler/automation tick runs.
6. Open `/admin/logs`.

SAMPLE DATA:
- Name: Auto Hold Expire
- Phone: 09171230021
- Party Size: 2

TRIGGER ACTION:
Let the notified hold pass its expiration time while automation is running.

EXPECTED RESULT:
The hold is cancelled/expired, the table is released, and an automation log is recorded.

HOW TO VERIFY:
Check `/admin/waitlist` for the guest leaving "Texted, on hold", `/admin/tables` for the table returning to Free, and `/admin/logs` for a `queue_holds` entry.

TEST CASE ID: AUT-002
MODULE: Automation
ROLE: Admin
PAGE/URL: /admin/waitlist
STEPS:
1. Login as admin.
2. Make sure automation is on in `/admin/settings?modal=devices`.
3. Create a waiting guest from `/staff/queue`.
4. Make the wait longer by occupying/reserving compatible tables on `/admin/tables` or adding more queue guests ahead of this one.
5. Wait for the automation tick.
6. Open `/admin/logs`.

SAMPLE DATA:
- Name: Wait Estimate Guest
- Phone: 09171230022
- Party Size: 4

TRIGGER ACTION:
Let the `wait_estimates` automation run after the queue wait increases.

EXPECTED RESULT:
The guest wait estimate is recalculated and an extended-wait SMS is sent when the configured threshold is met.

HOW TO VERIFY:
Check the waitlist display, Semaphore/SMS report, and `/admin/logs` for a `wait_estimates` entry.

TEST CASE ID: AUT-003
MODULE: Automation
ROLE: Admin
PAGE/URL: /admin/settings?modal=timing
STEPS:
1. Login as admin.
2. Open `/admin/settings?modal=devices` and make sure "Turn automation on" is checked.
3. Open `/admin/settings?modal=timing` and set "No-show (min)" to the smallest allowed value for testing.
4. Create or use a paid/active reservation that is past its booking time and not checked in.
5. Wait for the `no_shows` automation tick.
6. Open `/admin/waitlist` and `/admin/logs`.

SAMPLE DATA:
- Name: Auto No Show
- Phone: 09171230023
- Party Size: 2
- Reservation Time: Past the no-show grace period

TRIGGER ACTION:
Let the `no_shows` automation run after the reservation becomes overdue.

EXPECTED RESULT:
The booking is marked no-show/cancelled, the table is released, SMS is sent, and the next queue entry may be notified.

HOW TO VERIFY:
Check `/admin/logs` for `no_shows`, `/admin/tables` for the released table, and `/admin/bookings` for the booking status.

TEST CASE ID: AUT-004
MODULE: Automation
ROLE: Admin
PAGE/URL: /admin/waitlist
STEPS:
1. Login as admin.
2. Open `/admin/waitlist`.
3. Scroll to "Reservations (awaiting check-in)".
4. Find an eligible overdue reservation.
5. Click "Mark as No-show".
6. Confirm the prompt.

SAMPLE DATA:
- Name: Manual No Show
- Phone: 09171230024
- Party Size: 2

TRIGGER ACTION:
Click "Mark as No-show" for the overdue reservation.

EXPECTED RESULT:
The reservation is immediately marked no-show/cancelled, the table is released, and the no-show SMS is sent.

HOW TO VERIFY:
Confirm the reservation disappears from awaiting check-in, the table is Free on `/admin/tables`, and an automation/no-show log appears.

TEST CASE ID: AUT-005
MODULE: Automation
ROLE: Admin
PAGE/URL: /admin/focus
STEPS:
1. Login as admin.
2. Make sure automation is on in `/admin/settings?modal=devices`.
3. Create or use today's paid/active reservation that is past the late check-in grace period and has not been checked in.
4. Do not click "Check In".
5. Wait for the `late_checkin` automation tick.
6. Open `/admin/logs`.

SAMPLE DATA:
- Name: Late Checkin Guest
- Phone: 09171230025
- Party Size: 2
- Reservation Time: Today, already late

TRIGGER ACTION:
Let the `late_checkin` automation run while the guest remains unchecked-in.

EXPECTED RESULT:
A late check-in SMS is dispatched, timestamp stored, and automation log recorded.

HOW TO VERIFY:
Check Semaphore/SMS report and `/admin/logs` for `late_checkin`. The reservation should still be awaiting check-in until staff checks it in or no-show applies.

TEST CASE ID: AUT-006
MODULE: Automation
ROLE: Admin
PAGE/URL: /reservation
STEPS:
1. As customer, create a paid reservation scheduled about 24 hours from now.
2. As admin, make sure automation is on in `/admin/settings?modal=devices`.
3. Keep the scheduler/automation running.
4. Wait until the reminder window is reached.
5. Open `/admin/logs`.

SAMPLE DATA:
- Name: Reminder 24h Guest
- Phone: 09171230026
- Party Size: 2
- Reservation Time: About 24 hours from now

TRIGGER ACTION:
Let the `reminders` automation run inside the 24-hour reminder window.

EXPECTED RESULT:
A 24-hour reservation reminder SMS is sent and recorded.

HOW TO VERIFY:
Check the customer phone/Semaphore report and `/admin/logs` for a `reminders` entry marked 24h reminder.

TEST CASE ID: AUT-007
MODULE: Automation
ROLE: Admin
PAGE/URL: /reservation
STEPS:
1. As customer, create a paid reservation scheduled about 2 hours from now.
2. As admin, make sure automation is on in `/admin/settings?modal=devices`.
3. Keep the scheduler/automation running.
4. Wait until the 2-hour reminder window is reached.
5. Open `/admin/logs`.

SAMPLE DATA:
- Name: Reminder 2h Guest
- Phone: 09171230027
- Party Size: 2
- Reservation Time: About 2 hours from now

TRIGGER ACTION:
Let the `reminders` automation run inside the 2-hour reminder window.

EXPECTED RESULT:
A 2-hour reservation reminder SMS is sent and recorded.

HOW TO VERIFY:
Check the customer phone/Semaphore report and `/admin/logs` for a `reminders` entry marked 2h reminder.

TEST CASE ID: AUT-008
MODULE: Automation
ROLE: Admin
PAGE/URL: /admin/bookings
STEPS:
1. Login as admin.
2. Create or identify a reservation with an assigned reserved table.
3. Open `/admin/bookings`.
4. For a pending verification booking, click "Reject", or use a booking already marked cancelled/failed.
5. Wait for the `reservation_table_release` automation tick.
6. Open `/admin/tables` and `/admin/logs`.

SAMPLE DATA:
- Name: Release Failed Booking
- Phone: 09171230028
- Party Size: 2

TRIGGER ACTION:
Reject/fail/cancel a booking that had a reserved table, then let the release automation run.

EXPECTED RESULT:
The reserved table returns to Free and the booking no longer holds the table.

HOW TO VERIFY:
Check `/admin/tables` for the Free table and `/admin/logs` for `reservation_table_release`.

TEST CASE ID: AUT-009
MODULE: Automation
ROLE: Admin
PAGE/URL: /admin/settings?modal=devices
STEPS:
1. Login as admin.
2. Open `/admin/settings?modal=devices`.
3. Uncheck "Turn automation on" and click "Save".
4. Create a waiting guest and click "SMS" so it becomes "Texted, on hold".
5. Wait past the hold expiration.
6. Open `/admin/waitlist` and `/admin/logs`.

SAMPLE DATA:
- Name: Master Off Hold Guest
- Phone: 09171230029
- Party Size: 2

TRIGGER ACTION:
Let the queue hold expire while master automation is off.

EXPECTED RESULT:
Queue hold expiry still runs even when the master automation toggle is off.

HOW TO VERIFY:
Confirm the hold expires/releases and `/admin/logs` shows `queue_holds`, proving the hold-expiry task bypassed the master toggle.

TEST CASE ID: AUT-010
MODULE: Automation
ROLE: Admin
PAGE/URL: /admin/settings?modal=devices
STEPS:
1. Login as admin.
2. Open `/admin/settings?modal=devices`.
3. Uncheck "Turn automation on" and click "Save".
4. Create an overdue reservation or reminder/late-check-in condition.
5. Wait for the normal automation schedule.
6. Open `/admin/logs` and `/admin/bookings`.

SAMPLE DATA:
- Name: Master Off General Guest
- Phone: 09171230030
- Party Size: 2

TRIGGER ACTION:
Wait for general automation tasks while master automation is disabled.

EXPECTED RESULT:
General automation tasks such as no-shows, late check-in, reminders, and wait-estimate updates do not run.

HOW TO VERIFY:
Confirm no new `no_shows`, `late_checkin`, `reminders`, or `wait_estimates` log appears and the test booking remains unchanged.

TEST CASE ID: AUT-011
MODULE: Automation
ROLE: Admin
PAGE/URL: /admin/settings?modal=alerts
STEPS:
1. Login as admin.
2. Open `/admin/settings?modal=alerts`.
3. Enter a staff/admin Alert phone and click "Save".
4. Trigger a controlled automation failure in the test environment only, such as a deliberately broken automation dependency or test fixture that makes an automation task throw.
5. Open `/admin/logs` after the automation tick.

SAMPLE DATA:
- Name: Admin Alert Receiver
- Phone: 09171230031
- Party Size: N/A
- Alert Phone: 09171230031

TRIGGER ACTION:
Let the failing automation task run after alert phone is configured.

EXPECTED RESULT:
A failed automation log is recorded and an admin alert SMS is dispatched.

HOW TO VERIFY:
Check `/admin/logs` for a failed automation row and check the alert phone/Semaphore report for the automation error SMS. Do not use this on production data.

TEST CASE ID: PRI-001
MODULE: Priority Management
ROLE: Staff
PAGE/URL: /staff/queue
STEPS:
1. Login as staff.
2. Open `/staff/queue`.
3. Fill Name, Phone, and Party size.
4. Set Priority to PWD.
5. Click "Add to queue".
6. Open `/admin/waitlist`.

SAMPLE DATA:
- Name: Pedro PWD
- Phone: 09171230041
- Party Size: 2
- Priority: PWD

TRIGGER ACTION:
Click "Add to queue" with Priority set to PWD.

EXPECTED RESULT:
The guest is treated as priority and appears in the Priority section with a PWD badge.

HOW TO VERIFY:
Confirm Pedro PWD appears above regular queue guests. For strict score evidence, inspect the queue entry priority score in the database/test report.

TEST CASE ID: PRI-002
MODULE: Priority Management
ROLE: Staff
PAGE/URL: /staff/queue
STEPS:
1. Login as staff.
2. Open `/staff/queue`.
3. Fill Name, Phone, and Party size.
4. Set Priority to Pregnant.
5. Click "Add to queue".
6. Open `/admin/waitlist`.

SAMPLE DATA:
- Name: Ana Pregnant
- Phone: 09171230042
- Party Size: 2
- Priority: Pregnant

TRIGGER ACTION:
Click "Add to queue" with Priority set to Pregnant.

EXPECTED RESULT:
The guest is treated as priority and appears in the Priority section with a PREG badge.

HOW TO VERIFY:
Confirm Ana Pregnant appears above regular queue guests. For strict score evidence, inspect the queue entry priority score in the database/test report.

TEST CASE ID: PRI-003
MODULE: Priority Management
ROLE: Staff
PAGE/URL: /staff/queue
STEPS:
1. Login as staff.
2. Open `/staff/queue`.
3. Fill Name, Phone, and Party size.
4. Set Priority to Senior.
5. Click "Add to queue".
6. Open `/admin/waitlist`.

SAMPLE DATA:
- Name: Lolo Senior
- Phone: 09171230043
- Party Size: 2
- Priority: Senior

TRIGGER ACTION:
Click "Add to queue" with Priority set to Senior.

EXPECTED RESULT:
The guest is treated as priority and appears in the Priority section with an SC badge.

HOW TO VERIFY:
Confirm Lolo Senior appears above regular queue guests. For strict score evidence, inspect the queue entry priority score in the database/test report.

TEST CASE ID: PRI-004
MODULE: Priority Management
ROLE: Staff
PAGE/URL: /staff/queue
STEPS:
1. Login as staff.
2. Open `/staff/queue`.
3. Fill Name, Phone, and Party size.
4. Set Priority to Standard.
5. Click "Add to queue".
6. Open `/admin/waitlist`.

SAMPLE DATA:
- Name: Regular Guest
- Phone: 09171230044
- Party Size: 2
- Priority: Standard

TRIGGER ACTION:
Click "Add to queue" with Priority set to Standard.

EXPECTED RESULT:
The guest is treated as regular/non-priority.

HOW TO VERIFY:
Confirm the guest appears under Regular queue, not under Priority. For strict score evidence, inspect priority score = 0 in the database/test report.

TEST CASE ID: PRI-005
MODULE: Priority Management
ROLE: Staff
PAGE/URL: /admin/waitlist
STEPS:
1. Login as staff.
2. Create one Standard guest from `/staff/queue`.
3. Create one PWD, Pregnant, or Senior guest from `/staff/queue` after the regular guest.
4. Open `/admin/waitlist`.
5. Compare the Priority section to Regular queue.

SAMPLE DATA:
- Name: Priority Ahead Guest
- Phone: 09171230045
- Party Size: 2
- Priority: PWD
- Regular Comparison Phone: 09171230046

TRIGGER ACTION:
Refresh/open the waitlist after both guests exist.

EXPECTED RESULT:
Priority guests are displayed ahead of regular guests even if the regular guest joined first.

HOW TO VERIFY:
Confirm the priority guest appears in the Priority section above the Regular queue.

TEST CASE ID: PRI-006
MODULE: Priority Management
ROLE: Admin
PAGE/URL: /admin/settings?modal=devices
STEPS:
1. Login as admin.
2. Open `/admin/settings?modal=devices`.
3. Check "PWD line uses accessible tables only" and click "Save".
4. Create a PWD waitlist guest.
5. Open `/admin/waitlist` and click "Seat" or open the "Table" dropdown for that guest.

SAMPLE DATA:
- Name: Accessible PWD Guest
- Phone: 09171230047
- Party Size: 2
- Priority: PWD

TRIGGER ACTION:
Attempt to seat the PWD guest while the accessible-table setting is enabled.

EXPECTED RESULT:
Only compatible accessible tables should be offered/accepted for the PWD guest. If none exists, the UI should show no suitable free table.

HOW TO VERIFY:
Verify the "Table" dropdown/quick-seat list excludes non-accessible standard tables for that PWD guest.

TEST CASE ID: PRI-007
MODULE: Priority Management
ROLE: Admin
PAGE/URL: /admin/settings?modal=devices
STEPS:
1. Login as admin.
2. Open `/admin/settings?modal=devices`.
3. Uncheck "PWD line uses accessible tables only" and click "Save".
4. Create a PWD waitlist guest.
5. Open `/admin/waitlist` and click "Seat" or open the "Table" dropdown.

SAMPLE DATA:
- Name: Standard Allowed PWD
- Phone: 09171230048
- Party Size: 2
- Priority: PWD

TRIGGER ACTION:
Attempt to seat the PWD guest while the accessible-table setting is disabled.

EXPECTED RESULT:
Standard compatible tables are allowed for the PWD guest.

HOW TO VERIFY:
Verify a standard compatible table appears in the dropdown/quick-seat list and can be selected.

TEST CASE ID: PRI-008
MODULE: Priority Management
ROLE: Staff
PAGE/URL: /admin/waitlist
STEPS:
1. Login as staff.
2. Create a Senior waitlist guest from `/staff/queue`.
3. Open `/admin/waitlist`.
4. Click "Seat" or open the "Table" dropdown for the senior guest.
5. Choose a compatible standard table.

SAMPLE DATA:
- Name: Senior Standard Table
- Phone: 09171230049
- Party Size: 2
- Priority: Senior

TRIGGER ACTION:
Seat the senior guest at a compatible non-accessible table.

EXPECTED RESULT:
The system allows senior guests to use standard compatible tables.

HOW TO VERIFY:
Confirm the guest is seated and the selected table becomes Occupied.

TEST CASE ID: PRI-009
MODULE: Priority Management
ROLE: Staff
PAGE/URL: /admin/waitlist
STEPS:
1. Login as staff.
2. Create a Pregnant waitlist guest from `/staff/queue`.
3. Open `/admin/waitlist`.
4. Click "Seat" or open the "Table" dropdown for the pregnant guest.
5. Choose a compatible standard table.

SAMPLE DATA:
- Name: Pregnant Standard Table
- Phone: 09171230050
- Party Size: 2
- Priority: Pregnant

TRIGGER ACTION:
Seat the pregnant guest at a compatible non-accessible table.

EXPECTED RESULT:
The system allows pregnant guests to use standard compatible tables.

HOW TO VERIFY:
Confirm the guest is seated and the selected table becomes Occupied.

TEST CASE ID: PRI-010
MODULE: Priority Management
ROLE: Staff
PAGE/URL: /admin/waitlist
STEPS:
1. Login as staff.
2. Create any priority guest (PWD, Pregnant, or Senior).
3. Open `/admin/waitlist`.
4. Seat the priority guest at a compatible table.
5. Open `/admin/logs` for browser-visible activity; for strict compliance evidence, inspect the priority audit log file.

SAMPLE DATA:
- Name: Audit Priority Guest
- Phone: 09171230051
- Party Size: 2
- Priority: Senior

TRIGGER ACTION:
Seat a priority guest.

EXPECTED RESULT:
A priority seating audit event is written by the system.

HOW TO VERIFY:
Browser-visible verification: guest is seated and table becomes Occupied. Strict audit verification: check the priority audit log output for the priority seating event.

TEST CASE ID: ANL-001
MODULE: Analytics
ROLE: Admin
PAGE/URL: /admin/seating-analytics
STEPS:
1. Login as admin.
2. Create a customer reservation for today from `/reservation`, or use an existing reservation for today.
3. Open `/admin/seating-analytics`.
4. Look at the "Bookings today" card.

SAMPLE DATA:
- Name: Analytics Booking Today
- Phone: 09171230061
- Party Size: 2
- Reservation Date: Today

TRIGGER ACTION:
Open Seating analytics after today's booking exists.

EXPECTED RESULT:
"Bookings today" shows the correct count for reservations booked today.

HOW TO VERIFY:
Compare the number with today's records visible in `/admin/bookings`.

TEST CASE ID: ANL-002
MODULE: Analytics
ROLE: Admin
PAGE/URL: /admin/focus
STEPS:
1. Login as admin.
2. Create or use a paid active reservation for today.
3. Open `/admin/focus`.
4. In "Today's Reservations", click "Check In" for the booking.
5. Open `/admin/seating-analytics`.

SAMPLE DATA:
- Name: Analytics Checkin
- Phone: 09171230062
- Party Size: 2
- Reservation Date: Today

TRIGGER ACTION:
Click "Check In" on today's reservation.

EXPECTED RESULT:
"Checked in" count increases on the analytics page.

HOW TO VERIFY:
Compare `/admin/focus` after check-in and the "Checked in" card on `/admin/seating-analytics`.

TEST CASE ID: ANL-003
MODULE: Analytics
ROLE: Staff
PAGE/URL: /admin/waitlist
STEPS:
1. Login as staff.
2. Create a waitlist guest from `/staff/queue`.
3. Open `/admin/waitlist`.
4. Seat the guest at a compatible table.
5. Open `/admin/seating-analytics`.

SAMPLE DATA:
- Name: Analytics Queue Seat
- Phone: 09171230063
- Party Size: 2

TRIGGER ACTION:
Seat the queue guest.

EXPECTED RESULT:
"Seated from queue" count increases on the analytics page.

HOW TO VERIFY:
Confirm the guest is seated/removed from waitlist and the "Seated from queue" card reflects the seated queue entry.

TEST CASE ID: ANL-004
MODULE: Analytics
ROLE: Admin
PAGE/URL: /admin/tables
STEPS:
1. Login as admin.
2. Open `/admin/tables`.
3. Click a Free table.
4. Click "Seat Guests".
5. Open `/admin/seating-analytics`.

SAMPLE DATA:
- Name: N/A
- Phone: N/A
- Party Size: N/A
- Target Table Status: Free to Occupied

TRIGGER ACTION:
Click "Seat Guests" for a free table.

EXPECTED RESULT:
Current occupied table count increases.

HOW TO VERIFY:
Verify the Occupied count on `/admin/tables` and the occupied number in the "Tables Free / occupied" analytics card.

TEST CASE ID: ANL-005
MODULE: Analytics
ROLE: Admin
PAGE/URL: /admin/tables
STEPS:
1. Login as admin.
2. Open `/admin/tables`.
3. Click a Cleaning table and click "Mark Ready", or click a Reserved table and click "Free Table".
4. Open `/admin/seating-analytics`.

SAMPLE DATA:
- Name: N/A
- Phone: N/A
- Party Size: N/A
- Target Table Status: Cleaning or Reserved to Free

TRIGGER ACTION:
Click "Mark Ready" or "Free Table".

EXPECTED RESULT:
Current free table count increases.

HOW TO VERIFY:
Verify the Free count on `/admin/tables` and the free number in the "Tables Free / occupied" analytics card.

TEST CASE ID: ANL-006
MODULE: Analytics
ROLE: Admin
PAGE/URL: /admin/seating-analytics
STEPS:
1. Login as admin.
2. Create one or more reservations within the last 7 days; a reservation today is enough for a visible bar.
3. Open `/admin/seating-analytics`.
4. Look at "Bookings by hour - last 7 days".

SAMPLE DATA:
- Name: Peak Chart Guest
- Phone: 09171230066
- Party Size: 2
- Reservation Date: Today

TRIGGER ACTION:
Open the analytics page after recent booking data exists.

EXPECTED RESULT:
The 24-hour peak booking chart renders with booking counts grouped by hour.

HOW TO VERIFY:
Confirm the chart is visible, has bars/axis labels, and updates after new bookings are added.

TEST CASE ID: ANL-007
MODULE: Analytics
ROLE: Admin
PAGE/URL: /admin/seating-analytics
STEPS:
1. Login as admin.
2. Create or use reservations assigned to tables within the last 30 days.
3. Open `/admin/seating-analytics`.
4. Look at "Top 5 tables - last 30 days".

SAMPLE DATA:
- Name: N/A
- Phone: N/A
- Party Size: N/A
- Table Usage Data: Reservations assigned to tables

TRIGGER ACTION:
Open the analytics page after table booking data exists.

EXPECTED RESULT:
The Top 5 tables chart shows the most-used tables by reservation count.

HOW TO VERIFY:
Confirm table labels and counts appear in the chart and the highest-use table is listed first.

TEST CASE ID: ANL-008
MODULE: Analytics
ROLE: Admin
PAGE/URL: /admin/seating-analytics
STEPS:
1. Login as admin.
2. Open `/admin/seating-analytics`.
3. Look at the "Bookings by hour" chart.
4. Hover or move across the chart bars from left to right.

SAMPLE DATA:
- Name: N/A
- Phone: N/A
- Party Size: N/A
- Expected Labels: 12 AM through 11 PM

TRIGGER ACTION:
Render/interact with the peak-hour chart.

EXPECTED RESULT:
Peak-hour chart labels cover the full day from 12 AM to 11 PM.

HOW TO VERIFY:
Confirm the visible/tooltip labels progress through the 24-hour day without missing the first or last hour.

TEST CASE ID: ANL-009
MODULE: Analytics
ROLE: Admin
PAGE/URL: /admin/seating-analytics
STEPS:
1. Login as admin in a clean test database or a test state with no bookings/queue records.
2. Open `/admin/seating-analytics`.
3. Wait for the charts/cards to load.

SAMPLE DATA:
- Name: N/A
- Phone: N/A
- Party Size: N/A
- Data State: No bookings or queue records

TRIGGER ACTION:
Open the analytics page with no analytics data available.

EXPECTED RESULT:
The page loads gracefully with zero counts/empty charts instead of crashing.

HOW TO VERIFY:
Confirm the KPI cards show 0 where appropriate and the charts remain visible without error messages.

TEST CASE ID: ANL-010
MODULE: Analytics
ROLE: Staff
PAGE/URL: /admin/seating-analytics
STEPS:
1. Login as staff from `/admin/login`.
2. Manually type `/admin/seating-analytics` in the address bar.
3. Press Enter.

SAMPLE DATA:
- Name: N/A
- Phone: N/A
- Party Size: N/A
- Unauthorized Role: Staff

TRIGGER ACTION:
Attempt to open the analytics page as staff.

EXPECTED RESULT:
Access is blocked or redirected because analytics is admin-only.

HOW TO VERIFY:
Confirm the staff user does not see the Seating analytics page. If logged out, confirm the page redirects to login.
