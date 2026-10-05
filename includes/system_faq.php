<?php
$systemFaqs = [
 'Donation Eligibility' => [
  'Where is the Eligibility Checker?' => 'Sign in as a donor and open Eligibility. Complete the questionnaire before each day you register, and whenever your health changes. The result is preliminary; donation staff make the final assessment.',
  'What happens when staff review my screening?' => 'Needs Review requires staff assessment. Staff can approve, defer until a review date, or request another assessment. The Eligibility section shows their latest decision and notes. A dated deferral must end before registration.',
 ],
 'Campaigns & Registration' => [
  'How do I use Donation Locations?' => 'Combine keyword, venue, booking status and event date filters, then choose a sort order. Browsing a campaign does not complete your screening or create a booking.',
  'How do I submit a registration?' => 'Complete an eligible current screening, choose an open campaign, select an available time within its hours and agree to the requirements. A successful submission creates a Pending registration with a public reference.',
  'What do registration statuses mean?' => 'Pending awaits staff confirmation. Confirmed means the appointment is scheduled. Staff mark Checked-In on arrival and record Completed or Deferred after assessment. Cancelled and No-Show are retained in your records.',
  'Can I cancel a registration?' => 'Open My Donations, then Manage registrations. Pending and Confirmed bookings can be cancelled with a reason. You may have only one active registration.',
 ],
 'Donation Process' => [
  'Where can I see my donation history?' => 'Open My Donations. Staff-recorded outcomes appear in Donation Log & History. Completed donations remain visible during the waiting interval; your next eligible date controls when you can register again.',
 ],
 'Safety & Quality' => [
  'Does a successful screening guarantee acceptance?' => 'No. Staff assess you at the venue. Collected units remain Pending until authorized staff record required testing and processing decisions.',
 ],
 'HemoPulse System' => [
  'Where are Notifications?' => 'Open Notifications in the donor sidebar to read persistent updates. Temporary success and error alerts appear beside the relevant action and can be dismissed.',
  'How do I update my profile?' => 'Open My Account to upload a JPEG, PNG or WebP picture and edit your contact number. Name, email and role are displayed for reference; contact the team for changes to protected account details.',
  'How does the Newsletter work?' => 'Subscribe on Contact Us, then open the confirmation link delivered by email and confirm. You can stop updates using the unsubscribe link. In local preview mode the site explicitly tells you that no email was sent.',
  'How do I contact the team?' => 'Use the Contact Us form with your name, email, subject and message. Staff reply by email with a private conversation link where you can respond. Preview mode generates no email; delivery needs a configured mail service.',
 ],
];
foreach($systemFaqs as $category=>$questions): ?>
<section <?= $category==='Safety & Quality'?'id="safety"':'' ?>><h3><?= h($category) ?></h3>
<?php foreach($questions as $question=>$answer): ?><details class="faq-bar"><summary><span><?= h($question) ?></span><svg class="chevron" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></summary><div class="faq-content"><?= h($answer) ?></div></details><?php endforeach; ?>
</section><?php endforeach; ?>
