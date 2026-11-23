<?php
/**
 * Seed data for the Bongabong VMS.
 *
 * The Citizen's Charter services themselves are not here: install.php loads
 * them from sql/citizens-charter-2026.json (includes/charter_data.php), the
 * Handbook 2026 of all 25 offices. This file holds the Charter page text and
 * the FAQ.
 */

return [

'charter' => [
    'foreword' => "It is with great pride and deep sense of public service that we present the Municipal Government of Bongabong Citizen's Charter Handbook 2026 (1st Edition) - a comprehensive guide to the frontline services offered by the various offices and departments of the local government. This handbook has been prepared in compliance with Republic Act No. 11032, also known as the Ease of Doing Business and Efficient Government Service Delivery Act of 2018, which mandates all government agencies to regularly update and post their Citizen's Charter to promote transparency, accountability, and efficiency in public service delivery. The Citizen's Charter is our formal commitment to our constituents. It sets out the services we offer, the requirements we need from you, the fees to be paid, the time it takes to process your transactions, and the steps you need to follow. It also outlines your rights as a citizen and the standards of service you should expect from us. We recognize that quality public service is the hallmark of good governance. This handbook reflects our unwavering dedication to serve every Bongabongeño with honesty, efficiency, and compassion. We aim to make every transaction simple, fast, and hassle-free. We encourage all our constituents to familiarize themselves with this handbook. If you encounter any difficulty in availing our services or if a government employee fails to meet the standards prescribed herein, please do not hesitate to reach out through our feedback channels. Together, let us build a Bongabong that is progressive, peaceful, and truly responsive to the needs of its people.",
    'signatory_title'  => 'THE MUNICIPAL MAYOR',
    'signatory_office' => 'Municipal Government of Bongabong',
    'signatory_place'  => 'Oriental Mindoro, Philippines',
    'signatory_year'   => '2026',
    'vision'  => 'A progressive, God-centered, and sustainable municipality with empowered citizenry enjoying quality life in a peaceful and orderly community.',
    'mission' => 'To deliver efficient, effective, and responsive public services through transparent governance, sustainable development programs, and active citizen participation for the common good.',
    'address'        => 'Municipal Hall, Poblacion, Bongabong, Oriental Mindoro',
    'contact_number' => '09209696020',
    'email'          => 'municipalityofbongabong@gmail.com',
    'office_hours'   => 'Monday to Friday, 8:00 AM - 5:00 PM',
],

'faq' => [

    // ---- Visitor Registration ----------------------------------------
    [
        'How do I register as a visitor?',
        'Scan the QR code posted at the entrance of the Municipal Hall or tap the registration link on the front '
            . 'desk tablet, fill in the short form, and sign on the screen. When you tap Complete Registration, the '
            . 'screen shows your queue number, your reference number (for example VIS-0903-4817) and a QR code. '
            . 'Save or screenshot that code - it is what the counter scans to find you and to record your '
            . 'time out. Please take a '
            . 'photo of it and wait to be called. Registration is free and is done on the day of your visit, within '
            . 'office hours, Monday to Friday, 8:00 AM to 5:00 PM. / I-scan ang QR code sa pasukan, punan ang porma, at '
            . 'lumagda.',
        'Visitor Registration', 1,
    ],
    [
        'What information do I need to provide during registration?',
        'The form asks for your data privacy consent, your full name, sex, age, complete address, the purpose of '
            . 'your visit chosen from eight options such as See the Mayor, See the Municipal Administrator, Avail of '
            . 'Services, or Document Request, and '
            . 'your signature drawn on the screen. The contact number is optional; if you give one it must be exactly '
            . '11 digits, for example 09XXXXXXXXX. Nothing else is needed to register: requirements like the Police '
            . 'Clearance, Cedula, and Official Receipt belong to the service itself, such as the Mayor\'s Clearance.',
        'Visitor Registration', 2,
    ],
    [
        'How long does the registration process take?',
        'Filling in the form takes less than a minute, and there is no fee for registering. The time after that '
            . 'belongs to the service you came for, and each one is published in the Citizen\'s Charter: 25 minutes for '
            . 'a Certificate of Appearance, 45 minutes for a Job Application Endorsement, and 60 minutes for a Mayor\'s '
            . 'Clearance at PHP 100.00. / Wala pong bayad ang pagpaparehistro.',
        'Visitor Registration', 3,
    ],
    [
        'Do I need to register every time I visit?',
        'Yes. Every visit is logged on its own, so please register again each time you come to the Municipal '
            . 'Hall. / Opo, sa bawat pagbisita. Each registration gives you a new queue number and a new reference '
            . 'number beginning with VIS, which you may enter on the Track a Submission page to see your time in, time '
            . 'out, and status; the record is kept for 365 days only, in line with the Data Privacy Act (RA 10173).',
        'Visitor Registration', 4,
    ],

    // ---- Mayor's Clearance -------------------------------------------
    [
        'What is a Mayor\'s Clearance?',
        'A Mayor\'s Clearance is a certification issued by the Office of the Mayor verifying that an individual or '
            . 'business has no pending case or obligation with the Municipal Government. It may be availed by any '
            . 'bonafide citizen of the Municipality of Bongabong. / Patunay na wala kayong nakabinbing kaso o '
            . 'obligasyon sa Pamahalaang Bayan.',
        'Mayor\'s Clearance', 5,
    ],
    [
        'What are the requirements for Mayor\'s Clearance?',
        'Bring three things: 1 original copy of your Police Clearance from the Municipal Police Station, 1 '
            . 'original copy of your Cedula from the Municipal Treasurer\'s Office, and the Original Receipt of your '
            . 'payment, also issued by the Municipal Treasurer\'s Office. The document is prepared only after the '
            . 'Official Receipt is submitted to the Office of the Mayor. / Police Clearance, Cedula, at ang resibo ng '
            . 'bayad.',
        'Mayor\'s Clearance', 6,
    ],
    [
        'How much does a Mayor\'s Clearance cost?',
        'PHP 100.00, paid at the Office of the Municipal Treasurer, where the cashier issues your Official '
            . 'Receipt. It is the only service in this Citizen\'s Charter with a fee; the other eight services are free. '
            . '/ Isang daang piso (PHP 100.00), bayad sa Municipal Treasurer\'s Office.',
        'Mayor\'s Clearance', 7,
    ],
    [
        'How long does it take to get a Mayor\'s Clearance?',
        '60 minutes, or one hour in total, if your requirements are complete: 15 minutes to check your documents, '
            . '20 minutes to pay at the Office of the Municipal Treasurer, 15 minutes to prepare the clearance, and 10 '
            . 'minutes to fill up the logbook and claim it. Please come within office hours, Monday to Friday, 8:00 AM '
            . 'to 5:00 PM, at the Municipal Hall, Poblacion, Bongabong. / Humigit-kumulang isang oras kung kumpleto ang '
            . 'mga kailangan.',
        'Mayor\'s Clearance', 8,
    ],

    // ---- MEAP (Municipal Educational Assistance Program) -------------
    [
        'What is MEAP?',
        'MEAP is the Municipal Educational Assistance Program of the Office of the Mayor, listed as Service 6 in '
            . 'the Citizen\'s Charter. It processes educational financial aid for qualified students residing in the '
            . 'Municipality of Bongabong, and there is no fee to apply. Applications are handled by the MEAP '
            . 'Coordinator at the Municipal Hall, Poblacion, Bongabong, Oriental Mindoro, Monday to Friday, 8:00 AM to '
            . '5:00 PM.',
        'MEAP (Municipal Educational Assistance Program)', 9,
    ],
    [
        'Who can apply for MEAP?',
        'MEAP is for indigent and deserving students of the Municipality of Bongabong. Because a Certificate of '
            . 'Indigency is required, you must be a resident of your barangay for at least six months and secure it '
            . 'from the Barangay Captain\'s Office. / Para po ito sa mga mahihirap at karapat-dapat na mag-aaral ng '
            . 'Bongabong.',
        'MEAP (Municipal Educational Assistance Program)', 10,
    ],
    [
        'What are the basic requirements for MEAP?',
        'Bring one duly accomplished MEAP Application Form from the MEAP Coordinator, one photocopy of your Birth '
            . 'Certificate, one photocopy of your High School Diploma, 3 copies of your Certificate of Enrollment or '
            . 'Registration and 3 copies of your Certificate of Grades from the previous semester (both from the school '
            . 'or university Registrar\'s Office), 3 photocopies of your ID, one 2x2 latest ID picture taken within the '
            . 'last 6 months, and a Certificate of Indigency from the Barangay Captain\'s Office. If the papers are '
            . 'complete you are given an Applicant Number at once; if not, you will be asked to complete them first. / '
            . 'Kumpletuhin po ang mga dokumento bago magsumite.',
        'MEAP (Municipal Educational Assistance Program)', 11,
    ],
    [
        'How long does the MEAP application process take?',
        'The full process takes 38 working days and 20 minutes, and it is free. Only the first 20 minutes are '
            . 'spent at the counter, when your requirements are received and reviewed; the rest is the wait for the '
            . 'examination schedule (30 working days), the examination itself (1 day), the checking of results and the '
            . 'in-house interview (3 days), the posting of qualified applicants on the official Facebook page (3 days), '
            . 'and the orientation with the Memorandum of Agreement (1 day).',
        'MEAP (Municipal Educational Assistance Program)', 12,
    ],

    // ---- MAIFIPP (Medical Assistance Program) ------------------------
    [
        'What is MAIFIPP?',
        'MAIFIPP is the Medical Assistance for Indigent and Financially Incapacitated Patients Program of the '
            . 'Office of the Mayor - Education and Social Services Unit. It processes applications for medical and '
            . 'financial assistance from the LGU and coordinates with national agencies such as PCSO and DOH and with '
            . 'the hospital where the patient is admitted. The complete process takes 6 working days, 1 hour and 30 '
            . 'minutes, and it is free.',
        'MAIFIPP (Medical Assistance Program)', 13,
    ],
    [
        'Who can avail of MAIFIPP?',
        'Indigent and financially incapacitated patients residing in the Municipality of Bongabong. Residency and '
            . 'indigency are shown through the Certificate of Indigency issued by the Barangay Hall where the patient '
            . 'lives. / Para po ito sa mga maralitang pasyente na naninirahan sa Bongabong.',
        'MAIFIPP (Medical Assistance Program)', 14,
    ],
    [
        'What documents do I need for MAIFIPP?',
        'Five documents: the duly accomplished MAIFIPP Application Form from the Malasakit Center or Social '
            . 'Services Unit, a Clinical Abstract or Medical Certificate from the attending physician, the Statement of '
            . 'Account from the billing department of the hospital where the patient is admitted, a Certificate of '
            . 'Indigency from the Barangay Hall where the patient resides, and a Birth Certificate. Please bring them '
            . 'complete, because the first step is a 30-minute check of the documents before the interview can proceed.',
        'MAIFIPP (Medical Assistance Program)', 15,
    ],
    [
        'Is there a fee for MAIFIPP services?',
        'No. MAIFIPP is free, with no payment at any step. The total time is 6 working days, 1 hour and 30 '
            . 'minutes: 30 minutes to receive and check your documents, 1 hour for the interview and assessment, 5 '
            . 'working days for processing and coordination, and 1 day for the release of the guarantee letter or '
            . 'referral. / Walang bayad po.',
        'MAIFIPP (Medical Assistance Program)', 16,
    ],

    // ---- General Services --------------------------------------------
    [
        'What are the office hours of the Mayor\'s Office?',
        'The Office of the Mayor is open Monday to Friday, 8:00 AM to 5:00 PM, except on public holidays. Please '
            . 'come early enough for the service you need, as the Mayor\'s Clearance alone takes 60 minutes to complete. '
            . '/ Lunes hanggang Biyernes, 8:00 ng umaga hanggang 5:00 ng hapon.',
        'General Services', 17,
    ],
    [
        'Where is the Municipal Hall located?',
        'The Municipal Hall is at Poblacion, Bongabong, Oriental Mindoro, and the Office of the Mayor is inside '
            . 'it. Most offices in the Citizen\'s Charter are in the Municipal Hall too; the Services Directory '
            . 'gives the address of each one.',
        'General Services', 18,
    ],
    [
        'How can I contact the Mayor\'s Office?',
        'You may call 09209696020 or email municipalityofbongabong@gmail.com during office hours, Monday to '
            . 'Friday, 8:00 AM to 5:00 PM. You may also come to the Office of the Mayor at the Municipal Hall, '
            . 'Poblacion, Bongabong, Oriental Mindoro. If you are raising a concern and want a written reply, submit it '
            . 'through the concern form on this site and keep the reference number shown (for example CON-2026-00123) '
            . 'so you can track it.',
        'General Services', 19,
    ],
    [
        'What is a Certificate of Appearance?',
        'It is a document from the Office of the Mayor certifying that you personally appeared at the Municipal '
            . 'Hall for a valid transaction with the Mayor or with another office of the LGU. It is usually requested '
            . 'by private sector representatives and by staff of other LGUs and government offices. Present a valid ID '
            . 'and your company or government ID at the front desk of the Office of the Mayor, and the certificate is '
            . 'released in 25 minutes.',
        'General Services', 20,
    ],
    [
        'Is there a fee for Certificate of Appearance?',
        'None. The Certificate of Appearance is free, and the whole transaction takes 25 minutes at the front '
            . 'desk of the Office of the Mayor: your ID is checked first, then the certificate is released and you sign '
            . 'the logbook. / Wala pong bayad ang Certificate of Appearance.',
        'General Services', 21,
    ],

    // ---- Feedback and Complaints -------------------------------------
    [
        'How can I submit feedback about municipal services?',
        'Use the Feedback Form on this site: give your name, email address, and message, and choose the type of '
            . 'feedback - Suggestion, Commendation, Comment, Service Improvement, Website / System, or Others. A '
            . 'contact number is optional. When you submit, the system gives you a reference number such as '
            . 'FBK-2026-00123; you may also email municipalityofbongabong@gmail.com, call 09209696020, or come to the '
            . 'Municipal Hall, Poblacion, Bongabong, Oriental Mindoro, Monday to Friday, 8:00 AM - 5:00 PM. / Itago po '
            . 'ang reference number.',
        'Feedback and Complaints', 22,
    ],
    [
        'Where can I file a complaint or report an issue?',
        'Use the Other Concern Form on this site and choose the type - Complaint, Service Delay, Staff Conduct, '
            . 'Facility / Utilities, Documentation Issue, Fees and Charges, Request for Assistance, or Others - then '
            . 'give a short subject and a description of what happened. Your concern is logged with a reference number '
            . 'such as CON-2026-00123, endorsed to the responsible office, and normally answered within 3 working days. '
            . 'You may also report it in person at the Municipal Hall, Poblacion, Bongabong, Oriental Mindoro, Monday '
            . 'to Friday, 8:00 AM - 5:00 PM, or call 09209696020.',
        'Feedback and Complaints', 23,
    ],
    [
        'Can I submit a customer satisfaction survey?',
        'Opo. The Client Satisfaction Measurement (CSM) survey is on the Customer Satisfaction Survey page of '
            . 'this site and runs in Google Forms, so you can answer it on your phone right after your transaction. '
            . 'Answering is voluntary and your answers stay confidential. / Maaari ring piliin na hindi sagutan ang '
            . 'sarbey na ito.',
        'Feedback and Complaints', 24,
    ],
    [
        'Will my feedback be reviewed?',
        'Opo. Every submission is received by the Municipality of Bongabong and moves through Received, Under '
            . 'Review, In Progress, and Resolved, and any official response is posted on the Track a Submission page - '
            . 'enter your reference number, for example FBK-2026-00123 for feedback or CON-2026-00123 for a concern. '
            . 'Concerns are normally answered within 3 working days, and your personal details are kept for 365 days '
            . 'only, in line with the Data Privacy Act. / Binabasa po ang bawat isinumite.',
        'Feedback and Complaints', 25,
    ],

    // ---- Document Processing -----------------------------------------
    [
        'How long does it take to process documents requiring the Mayor\'s signature?',
        'Documents brought to the Office of the Mayor for the Local Chief Executive\'s signature take 2 days and '
            . '75 minutes in total, and there is no fee. The liaison officer submits the papers and they are logged for '
            . 'routing (30 minutes), forwarded to the Mayor for signature (2 days), collected after signing (15 '
            . 'minutes), then released against the logbook (30 minutes). This service is for requesting offices within '
            . 'the LGU. / Dalawang araw at 75 minuto po ang kabuuan, at libre ito.',
        'Document Processing', 26,
    ],
    [
        'Can I get a job application endorsement from the Mayor\'s Office?',
        'Yes. The Office of the Mayor issues Job Application Endorsements free of charge and the whole process '
            . 'takes 45 minutes, for any individual applying for a position in a national government agency. Bring a '
            . 'valid ID and your pertinent documents; there is a short interview on your personal details and '
            . 'educational and career background before the endorsement letter is prepared and signed. / Opo, libre po '
            . 'ito at 45 minuto ang proseso.',
        'Document Processing', 27,
    ],
    [
        'What is the process for correspondence of administrative documents?',
        'Submit your written request or request form with the supporting documents (for example an ID for a '
            . 'certification, or a project proposal for a MOA) to the Office of the Mayor - Education and Social '
            . 'Services Unit. The request is received, logged, and assessed in 15 minutes, the draft is prepared and '
            . 'routed for the proper signature within 3 working days, and the letter is then forwarded to the concerned '
            . 'office in 10 minutes. The total is 3 working days and 25 minutes, with no fee; office hours are Monday '
            . 'to Friday, 8:00 AM to 5:00 PM.',
        'Document Processing', 28,
    ],

],

];
