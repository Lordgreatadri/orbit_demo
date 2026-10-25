<x-public.legal-page
    title="Terms and Conditions"
    description="Terms and Conditions for using the ORBIT platform."
    draft-notice="This document is a working draft prepared for the product owner's legal review. It does not constitute legal advice and must be reviewed before production use."
    :sections="[
        'service' => '1. The service',
        'accounts' => '2. Accounts and organisations',
        'use' => '3. Acceptable use',
        'data' => '4. Customer data',
        'availability' => '5. Availability and support',
        'fees' => '6. Fees',
        'termination' => '7. Suspension and termination',
        'liability' => '8. Liability',
        'changes' => '9. Changes',
        'law' => '10. Governing law',
        'contact' => 'Contact',
    ]"
>
    <h2 id="service">1. The service</h2>
    <p>ORBIT ("the Service") is a web-based platform for recording attendance, managing schedules and leave, and providing operational visibility to organisations ("Customers"). The Service is provided by the ORBIT operator ("we", "us").</p>

    <h2 id="accounts">2. Accounts and organisations</h2>
    <p>An organisation account is created by a responsible contact who confirms they are authorised to act for the organisation. The responsible contact becomes the initial organisation administrator and is responsible for inviting and managing further users. Users must keep their credentials confidential and notify us of any suspected unauthorised use.</p>

    <h2 id="use">3. Acceptable use</h2>
    <p>Customers agree not to misuse the Service, including attempting to access another organisation's data, circumventing attendance verification, submitting false location information, or using the Service in breach of applicable law.</p>

    <h2 id="data">4. Customer data</h2>
    <p>Customers own the data they and their users submit. We process it to provide the Service as described in the <a href="{{ route('privacy') }}">Privacy Policy</a>. Customers are responsible for having a lawful basis to record attendance and location information about their members and for informing them accordingly.</p>

    <h2 id="availability">5. Availability and support</h2>
    <p>We aim to keep the Service available but do not guarantee uninterrupted operation. Planned maintenance will be communicated where practicable. Support is provided via the contact channels published on this site.</p>

    <h2 id="fees">6. Fees</h2>
    <p>Any fees, trial periods, or subscription terms will be agreed separately in writing. No billing functionality is active in the current release.</p>

    <h2 id="termination">7. Suspension and termination</h2>
    <p>We may suspend or close an organisation account for material breach of these Terms, for security reasons, or where required by law. Customers may request closure at any time; data handling on closure is described in the Privacy Policy.</p>

    <h2 id="liability">8. Liability</h2>
    <p>To the extent permitted by law, the Service is provided "as is" and we are not liable for indirect or consequential loss. Nothing in these Terms excludes liability that cannot lawfully be excluded.</p>

    <h2 id="changes">9. Changes</h2>
    <p>We may update these Terms. Material changes will be notified to organisation administrators, and the version label above will change.</p>

    <h2 id="law">10. Governing law</h2>
    <p>To be confirmed by the product owner's legal review.</p>

    <h2 id="contact">Contact</h2>
    <p>Questions about these Terms can be sent via the <a href="{{ route('contact') }}">contact page</a>.</p>
</x-public.legal-page>
