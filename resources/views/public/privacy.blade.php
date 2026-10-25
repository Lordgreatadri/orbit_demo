<x-public.legal-page
    title="Privacy Policy"
    description="How ORBIT collects, uses, and protects personal information."
    draft-notice="This policy is a working draft prepared for the product owner's legal and data-protection review before production use."
    :sections="[
        'who' => '1. Who we are',
        'collect' => '2. Information we collect',
        'use' => '3. How we use information',
        'sharing' => '4. Sharing',
        'retention' => '5. Retention',
        'security' => '6. Security',
        'rights' => '7. Your rights',
        'cookies' => '8. Cookies and local storage',
        'changes' => '9. Changes',
        'contact' => 'Contact',
    ]"
>
    <h2 id="who">1. Who we are</h2>
    <p>ORBIT is operated by the ORBIT operator. For organisation members, the organisation that invited you is the controller of attendance and scheduling data; ORBIT processes that data on the organisation's behalf.</p>

    <h2 id="collect">2. Information we collect</h2>
    <ul>
        <li><strong>Account information</strong> — name, e-mail address, phone number, role, and organisation.</li>
        <li><strong>Attendance records</strong> — check-in and check-out times, the method used (for example QR session or GPS), the location or geofence involved, and the verification outcome.</li>
        <li><strong>Location data</strong> — only when you explicitly start a location-verified attendance action. We collect a single position and its reported accuracy for that action. ORBIT does not track location continuously or in the background.</li>
        <li><strong>Usage and security data</strong> — sign-in events, IP address, and audit entries for administrative actions.</li>
        <li><strong>Contact form messages</strong> — the details you provide when contacting us.</li>
    </ul>

    <h2 id="use">3. How we use information</h2>
    <ul>
        <li>To provide attendance, scheduling, leave, and reporting features to your organisation.</li>
        <li>To secure accounts, prevent abuse, and investigate incidents.</li>
        <li>To send service e-mails such as verification, password reset, invitations, and notifications your organisation enables.</li>
        <li>To respond to enquiries.</li>
    </ul>

    <h2 id="sharing">4. Sharing</h2>
    <p>Your data is visible to authorised users of your own organisation according to their role. It is not visible to other organisations. We use service providers (for example e-mail delivery and hosting) under contracts that restrict their use of data. We do not sell personal data.</p>

    <h2 id="retention">5. Retention</h2>
    <p>Account and attendance data are retained while your organisation uses the Service and for a period after closure agreed with the organisation. Location data is retained only as part of the attendance record it verified. Contact form messages are kept only as long as needed to respond. Specific retention periods will be confirmed in the legal review.</p>

    <h2 id="security">6. Security</h2>
    <p>We use encrypted connections, hashed passwords, rate limiting, role-based access, organisation-level data separation, and audit logging. No system is perfectly secure; please report concerns via the contact page.</p>

    <h2 id="rights">7. Your rights</h2>
    <p>Depending on your jurisdiction you may have rights to access, correct, or delete your personal data, or to object to processing. Organisation members should contact their organisation administrator first; you may also contact us.</p>

    <h2 id="cookies">8. Cookies and local storage</h2>
    <p>ORBIT uses a session cookie for sign-in and CSRF protection, and stores your theme preference in your browser's local storage. No advertising or cross-site tracking cookies are used.</p>

    <h2 id="changes">9. Changes</h2>
    <p>We will notify organisation administrators of material changes and update the version label above.</p>

    <h2 id="contact">Contact</h2>
    <p>Privacy questions can be sent via the <a href="{{ route('contact') }}">contact page</a>.</p>
</x-public.legal-page>
