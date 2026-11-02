# KarnaRide Admin — how to use every module

# KarnaRide एडमिन — हर मॉड्यूल कैसे इस्तेमाल करें

Login: the admin site (local `http://127.0.0.1:8001`, live `https://admin.karnaride.in`).

लॉगिन एडमिन साइट पर करें।

You only see a menu if your role has that permission. Super Admin sees everything. A State Head sees their state. A District Head sees their district. A Fleet Owner sees only their own fleet.

आपको वही मेनू दिखेगा जिसकी परमिशन आपके रोल में है। सुपर एडमिन सब देखता है। स्टेट हेड अपना राज्य, डिस्ट्रिक्ट हेड अपना जिला, फ्लीट ओनर सिर्फ अपना फ्लीट देखता है।

---

## Dashboard / डैशबोर्ड

English: Today’s picture of bookings, drivers, and money.

Hindi: आज की बुकिंग, ड्राइवर और पैसे की तस्वीर।

How: Open Dashboard after login. Use it to see what needs attention. Do not change records here.

कैसे: लॉगिन के बाद डैशबोर्ड खोलें। यहाँ सिर्फ देखें, रिकॉर्ड न बदलें।

---

## Organization / संगठन

### States / राज्य

English: Add or edit states where KarnaRide works.

Hindi: जिन राज्यों में सेवा है, उन्हें जोड़ें या बदलें।

How: Organization → States → add name → Save.

कैसे: Organization → States → नाम लिखें → Save।

### Districts / जिले

English: Districts inside a state. Bookings and heads are tied to a district.

Hindi: राज्य के जिले। बुकिंग और हेड जिले से जुड़े होते हैं।

How: Organization → Districts → pick the state → add the district → Save.

कैसे: Organization → Districts → राज्य चुनें → जिला जोड़ें → Save।

### All users / सभी यूजर

English: Every login: customer, driver, fleet owner, state head, district head, admin.

Hindi: हर लॉगिन: कस्टमर, ड्राइवर, फ्लीट ओनर, स्टेट हेड, डिस्ट्रिक्ट हेड, एडमिन।

How: Search by name or phone. Open the user to see the profile. Change role or status only if you are allowed.

कैसे: नाम या फोन से खोजें। प्रोफाइल खोलें। रोल या स्टेटस तभी बदलें जब परमिशन हो।

### State & district heads / स्टेट और डिस्ट्रिक्ट हेड

English: People who run one state or one district.

Hindi: जो एक राज्य या एक जिले को चलाते हैं।

How: Assign the person to the correct state or district. They then see only that territory.

कैसे: व्यक्ति को सही राज्य या जिले पर लगाएँ। उसके बाद उसे सिर्फ वही इलाका दिखेगा।

### Territory contacts / इलाके के संपर्क

English: Phone and name shown for a territory.

Hindi: इलाके का नाम और फोन जो ऐप में दिख सकता है।

How: Add the contact, then save. Delete only a wrong contact.

कैसे: संपर्क जोड़कर सेव करें। गलत संपर्क ही हटाएँ।

### State Head KYC / District Head KYC / Fleet Owner KYC

English: Check ID documents before you approve that person.

Hindi: मंजूरी से पहले उनके दस्तावेज देखें।

How: Open the KYC list → open the file preview → Approve or Reject. Do not approve a blurry or wrong document.

कैसे: KYC लिस्ट खोलें → फाइल देखें → Approve या Reject। धुंधला या गलत दस्तावेज अप्रूव न करें।

---

## Fleet / फ्लीट

### Fleet Owners / फ्लीट ओनर

English: A person who owns many vehicles.

Hindi: जिसके पास कई गाड़ियाँ हैं।

How: Create the fleet owner, then add their vehicles and drivers under that fleet.

कैसे: फ्लीट ओनर बनाएँ, फिर उसी फ्लीट में गाड़ी और ड्राइवर जोड़ें।

### Drivers / ड्राइवर

English: Captains who take trips in the driver app.

Hindi: ड्राइवर ऐप में ट्रिप लेने वाले कप्तान।

How: Search the driver. Check duty, vehicle, and KYC. Block or suspend only with a reason.

कैसे: ड्राइवर खोजें। ड्यूटी, गाड़ी और KYC देखें। ब्लॉक या सस्पेंड सिर्फ वजह के साथ करें।

### Driver KYC

English: Same driver list, used to approve licence, RC, and photo.

Hindi: वही ड्राइवर लिस्ट, लाइसेंस, RC और फोटो अप्रूव करने के लिए।

How: Open the driver → check each document → Approve. The driver cannot take jobs until KYC is approved.

कैसे: ड्राइवर खोलें → हर दस्तावेज देखें → Approve। KYC अप्रूव हुए बिना जॉब नहीं मिलती।

### Vehicles / गाड़ियाँ

English: Registration number, category (bike, auto, sedan, SUV, ambulance), and which driver uses it.

Hindi: नंबर, कैटेगरी (बाइक, ऑटो, सेडान, SUV, एम्बुलेंस) और कौन सा ड्राइवर चलाता है।

How: Add the vehicle under the right fleet and category. Ambulance trips need category AMBULANCE.

कैसे: गाड़ी सही फ्लीट और कैटेगरी में जोड़ें। एम्बुलेंस ट्रिप के लिए कैटेगरी AMBULANCE होनी चाहिए।

### District vehicles / जिले की गाड़ियाँ

English: Which vehicles sit in which district.

Hindi: कौन सी गाड़ी किस जिले में है।

How: Filter by district and confirm the vehicle is in the right place.

कैसे: जिला चुनकर देखें कि गाड़ी सही जगह है।

---

## Operations / ऑपरेशन

### Bookings / बुकिंग

English: Every trip. Open one booking to see customer, driver, fare, and status.

Hindi: हर ट्रिप। एक बुकिंग खोलकर कस्टमर, ड्राइवर, किराया और स्टेटस देखें।

How: Search by booking id or phone. Open the booking. For bulk, corporate, and tour, record the driver payout here with amount, method, and UTR. This does not send money by itself.

कैसे: बुकिंग आईडी या फोन से खोजें। बुकिंग खोलें। बल्क, कॉरपोरेट और टूर में ड्राइवर का भुगतान यहीं लिखें: रकम, तरीका और UTR। इससे पैसा अपने आप नहीं जाता।

### Assign Drivers / ड्राइवर असाइन

English: Put a driver on a booking that is still waiting.

Hindi: जो बुकिंग ड्राइवर का इंतजार कर रही है, उस पर ड्राइवर लगाएँ।

How: Open the waiting booking → choose a driver who is online, verified, and in the right vehicle category → Assign.

कैसे: वेटिंग बुकिंग खोलें → ऑनलाइन, वेरिफाइड और सही कैटेगरी का ड्राइवर चुनें → Assign।

### Live Tracking / लाइव ट्रैकिंग

English: Map of drivers who are sending GPS.

Hindi: जिन ड्राइवरों का GPS आ रहा है, उनका नक्शा।

How: Open the map. A driver with old or missing GPS will not look nearby for a new trip.

कैसे: नक्शा खोलें। पुराना या खाली GPS होने पर वह ड्राइवर नई ट्रिप के लिए पास नहीं गिना जाता।

### Manual Booking / मैनुअल बुकिंग

English: Create a booking from the office when the customer called.

Hindi: जब कस्टमर ने फोन किया हो, ऑफिस से बुकिंग बनाएँ।

How: Fill pickup, drop, service, and customer → Save. Then assign a driver if the screen asks.

कैसे: पिकअप, ड्रॉप, सर्विस और कस्टमर भरें → Save। माँगे तो ड्राइवर असाइन करें।

### Bulk Bookings / बल्क बुकिंग

English: One customer, many vehicles, for an event.

Hindi: एक कस्टमर, कई गाड़ियाँ, किसी इवेंट के लिए।

How: Open the parent booking. Settle each driver on their own child trip. Do not pay the full parent amount to one driver.

कैसे: पैरेंट बुकिंग खोलें। हर ड्राइवर को उसकी अपनी ट्रिप पर सेटल करें। पूरी रकम एक ड्राइवर को न दें।

### Parcel / पार्सल

English: Parcel pickups and deliveries.

Hindi: पार्सल पिकअप और डिलीवरी।

How: Open the parcel. Check pickup, drop, and status. Pickup OTP and delivery PIN are handled in the apps.

कैसे: पार्सल खोलें। पिकअप, ड्रॉप और स्टेटस देखें। OTP और डिलीवरी PIN ऐप में होते हैं।

### Travel Packages / ट्रैवल पैकेज

English: Holiday packages you publish for the app and website.

Hindi: ऐप और वेबसाइट पर दिखने वाले टूर पैकेज।

How: Add only a package you really sell. Do not type a fake price.

कैसे: वही पैकेज जोड़ें जो सच में बिकता है। नकली कीमत न लिखें।

### Travel Bookings / ट्रैवल बुकिंग

English: Customers who booked a tour.

Hindi: जिन कस्टमर्स ने टूर बुक किया।

How: Open the booking, check payment, then assign the vehicle. Driver money is recorded by admin, not sent automatically.

कैसे: बुकिंग खोलें, पेमेंट देखें, फिर गाड़ी लगाएँ। ड्राइवर का पैसा एडमिन लिखता है, अपने आप नहीं जाता।

### Ambulance / एम्बुलेंस

English: Emergency trips. The booked fare starts at ₹0.

Hindi: इमरजेंसी ट्रिप। बुकिंग किराया ₹0 से शुरू होता है।

How: Assign an ambulance driver. After the trip, the driver reports the cash or UPI collected. Open Ambulance and press Verify. Do not change the amount the driver typed.

कैसे: एम्बुलेंस ड्राइवर लगाएँ। ट्रिप के बाद ड्राइवर जो रकम लिखे, उसे Verify करें। उसकी लिखी रकम न बदलें।

### Corporate Plans / कॉरपोरेट प्लान

English: Company plans and credit rules.

Hindi: कंपनी के प्लान और क्रेडिट नियम।

How: Set the plan the company is allowed to use. A credit booking should stay unconfirmed until you approve it.

कैसे: कंपनी को जो प्लान मिला है वही सेट करें। क्रेडिट बुकिंग आपकी मंजूरी से पहले कन्फर्म न हो।

### Corporate Booking / कॉरपोरेट बुकिंग

English: Trips booked by a company.

Hindi: कंपनी की ट्रिप।

How: Check the company, the fare, and the driver. Record settlement the same way as a normal company-collected trip.

कैसे: कंपनी, किराया और ड्राइवर देखें। सेटलमेंट वैसे ही लिखें जैसे कंपनी कलेक्शन वाली ट्रिप में।

### Corporate accounts / कॉरपोरेट अकाउंट

English: The company profile used for those bookings.

Hindi: उन बुकिंग के लिए कंपनी की प्रोफाइल।

How: Keep the company name and contact correct.

कैसे: कंपनी का नाम और संपर्क सही रखें।

### Safety & SOS / सेफ्टी और SOS

English: Emergency alerts from a trip.

Hindi: ट्रिप से आया इमरजेंसी अलर्ट।

How: Open the alert, call the people on the trip, and mark it handled when it is finished.

कैसे: अलर्ट खोलें, ट्रिप वाले लोगों को कॉल करें, निपटने के बाद हैंडल मार्क करें।

### Complaints / शिकायत

English: Customer or driver complaints.

Hindi: कस्टमर या ड्राइवर की शिकायत।

How: Read the complaint, reply, and close it when it is solved.

कैसे: शिकायत पढ़ें, जवाब दें, हल होने पर बंद करें।

### FAQ & support copy / FAQ

English: Help text shown in the app.

Hindi: ऐप में दिखने वाला हेल्प टेक्स्ट।

How: Edit a question and answer, then save. The app reads this text.

कैसे: सवाल और जवाब बदलकर सेव करें। ऐप यही टेक्स्ट पढ़ता है।

### Ratings / रेटिंग

English: Stars the customer gave after a trip.

Hindi: ट्रिप के बाद कस्टमर के स्टार।

How: Open a rating to see the booking and the driver. Use it to review service. Do not delete a rating to hide a problem.

कैसे: रेटिंग खोलकर बुकिंग और ड्राइवर देखें। समस्या छुपाने के लिए रेटिंग न मिटाएँ।

---

## Offers / ऑफर

### Coupons / कूपन

English: Discount codes for the customer app.

Hindi: कस्टमर ऐप के डिस्काउंट कोड।

How: Set the code, the amount, and the dates. Turn it off when the offer ends.

कैसे: कोड, रकम और तारीख सेट करें। ऑफर खत्म हो तो बंद कर दें।

### Advertising / विज्ञापन

English: Banners in the app and on the website.

Hindi: ऐप और वेबसाइट के बैनर।

How: Upload one banner, set where it shows, and publish. Delete an old banner from this screen.

कैसे: एक बैनर अपलोड करें, जगह चुनें, पब्लिश करें। पुराना बैनर यहीं से डिलीट करें।

### Popular routes / लोकप्रिय रूट

English: Routes highlighted for customers.

Hindi: कस्टमर्स को दिखाए जाने वाले रूट।

How: Add a route you actually run.

कैसे: वही रूट जोड़ें जो सच में चलता है।

### Website enquiries / वेबसाइट पूछताछ

English: Forms sent from karnaride.in.

Hindi: वेबसाइट से आए फॉर्म।

How: Open the enquiry and call or email the person. Mark it done after you reply.

कैसे: पूछताछ खोलें और व्यक्ति को कॉल या ईमेल करें। जवाब देने के बाद पूरा मार्क करें।

---

## Finance / पैसा

### Payments / पेमेंट

English: Customer payments that reached KarnaRide.

Hindi: कस्टमर के पेमेंट जो KarnaRide तक आए।

How: Search the booking. A payment is successful only after the server or the payment gateway confirms it.

कैसे: बुकिंग खोजें। पेमेंट तभी सफल है जब सर्वर या गेटवे पुष्टि करे।

### Wallet / वॉलेट

English: Customer and driver wallet entries.

Hindi: कस्टमर और ड्राइवर वॉलेट की एंट्री।

How: Open the person. Credits and debits are listed. Do not delete a row. If something is wrong, add a correcting entry.

कैसे: व्यक्ति खोलें। क्रेडिट और डेबिट की लिस्ट देखें। रो डिलीट न करें। गलती हो तो सुधार एंट्री जोड़ें।

### Revenue / रेवेन्यू

English: Earnings by state and district.

Hindi: राज्य और जिले के हिसाब से कमाई।

How: Pick the state or district and the date. The list must match that territory.

कैसे: राज्य या जिला और तारीख चुनें। लिस्ट उसी इलाके की होनी चाहिए।

### Commission / कमीशन

English: The company share of a fare.

Hindi: किराये में कंपनी का हिस्सा।

How: Read the split. City cash rides still take commission from the driver wallet because the driver held the cash.

कैसे: बंटवारा पढ़ें। सिटी कैश राइड में कमीशन ड्राइवर वॉलेट से कटता है क्योंकि नकद ड्राइवर के पास रहता है।

### Commission settlement / कमीशन सेटलमेंट

English: Pay a district or fleet commission after you approve it.

Hindi: मंजूरी के बाद जिला या फ्लीट कमीशन देना।

How: Open the pending row, check the amount, then mark it paid only after you have sent the money.

कैसे: पेंडिंग रो खोलें, रकम देखें, पैसा भेजने के बाद ही पेड मार्क करें।

### Withdraw requests / निकासी अनुरोध

English: A driver asked to take money out of the wallet.

Hindi: ड्राइवर ने वॉलेट से पैसा निकालने को कहा।

How: Check the amount and the bank details. Approve only if the wallet balance covers it. Reject if the details are wrong.

कैसे: रकम और बैंक डिटेल देखें। बैलेंस काफी हो तभी अप्रूव करें। डिटेल गलत हो तो रिजेक्ट करें।

### Fleet commission / फ्लीट कमीशन

English: How much of the district pool a fleet owner receives.

Hindi: जिले के पूल में से फ्लीट ओनर को कितना मिलेगा।

How: District settings → set the fleet cut → Save.

कैसे: District settings → फ्लीट कट सेट करें → Save।

Driver settlement for bulk, corporate, and tour is not a separate menu. It is on the booking page.

बल्क, कॉरपोरेट और टूर का ड्राइवर सेटलमेंट अलग मेनू नहीं है। वह बुकिंग पेज पर है।

---

## Reports / रिपोर्ट

English: Lists you can filter and export.

Hindi: फिल्टर और एक्सपोर्ट वाली लिस्ट।

How: Choose dates and the report type, then download if the button is there.

कैसे: तारीख और रिपोर्ट टाइप चुनें, बटन हो तो डाउनलोड करें।

---

## System / सिस्टम

### Notifications / नोटिफिकेशन

English: Push and in-app messages.

Hindi: पुश और ऐप के अंदर के संदेश।

How: Write the title and message, choose who receives it, then send.

कैसे: टाइटल और मैसेज लिखें, किसे भेजना है चुनें, फिर भेजें।

### Fare Management / किराया

English: Price rules for each service and vehicle.

Hindi: हर सर्विस और गाड़ी के किराये के नियम।

How: Edit the fare, save, and test one quote in the app. Old completed trips do not change.

कैसे: किराया बदलकर सेव करें और ऐप में एक कोट चेक करें। पुरानी पूरी ट्रिप नहीं बदलती।

### Ride Settings / राइड सेटिंग

English: Search radius, how long a driver request stays open, advance percent, and company-collection rules for bulk, corporate, and tour.

Hindi: सर्च रेडियस, रिक्वेस्ट का समय, एडवांस प्रतिशत, और बल्क, कॉरपोरेट, टूर का कंपनी कलेक्शन।

How: Set radius steps such as 5, 10, 15, 20. Set driver payable percent. Driver advance here is only a cap. It is not paid automatically.

कैसे: रेडियस स्टेप जैसे 5, 10, 15, 20 सेट करें। ड्राइवर पेएबल प्रतिशत सेट करें। ड्राइवर एडवांस सिर्फ सीमा है। अपने आप नहीं जाता।

### Service Management / सर्विस

English: Turn a service on or off.

Hindi: सर्विस चालू या बंद करें।

How: Disable a service only when you want the apps to stop offering it.

कैसे: सर्विस तभी बंद करें जब ऐप में वह ऑफर न होनी चाहिए।

### App account pages / ऐप अकाउंट पेज

English: Text inside the customer and driver account screens.

Hindi: कस्टमर और ड्राइवर ऐप के अकाउंट पेज का टेक्स्ट।

How: Edit and save. Open the app to confirm the new text.

कैसे: बदलकर सेव करें। ऐप खोलकर नया टेक्स्ट देखें।

### Website CMS / वेबसाइट CMS

English: Website page text.

Hindi: वेबसाइट के पेज का टेक्स्ट।

How: Edit the page and save. The public site reads it.

कैसे: पेज बदलकर सेव करें। पब्लिक साइट यही पढ़ती है।

### Website & branding / ब्रांडिंग

English: Logo and site name.

Hindi: लोगो और साइट का नाम।

How: Upload the official KarnaRide logo. Do not replace it with another brand.

कैसे: असली KarnaRide लोगो अपलोड करें। दूसरा ब्रांड न लगाएँ।

### Roles / रोल

English: Who can open which menu.

Hindi: कौन कौन सा मेनू खोल सकता है।

How: Open the role, tick only the permissions that job needs, and save. A person without the permission must not see that menu.

कैसे: रोल खोलें, उसी काम की परमिशन टिक करें, सेव करें। जिसकी परमिशन नहीं, उसे मेनू नहीं दिखना चाहिए।

### Settings / सेटिंग

English: General system switches.

Hindi: सिस्टम के सामान्य स्विच।

How: Change one setting, save, and check the app.

कैसे: एक सेटिंग बदलें, सेव करें, ऐप में जाँचें।

### Audit Logs / ऑडिट लॉग

English: Who changed what, and when.

Hindi: किसने क्या बदला और कब।

How: Search the user or the booking. Use this when something looks wrong. Do not delete log rows.

कैसे: यूजर या बुकिंग खोजें। कुछ गलत लगे तो यहीं देखें। लॉग रो डिलीट न करें।

### Old users / पुराने यूजर

English: Import of users from an older list.

Hindi: पुरानी लिस्ट से यूजर इंपोर्ट।

How: Use only when you are moving old records. Do not import the same file twice.

कैसे: पुराने रिकॉर्ड लाते समय ही इस्तेमाल करें। वही फाइल दो बार इंपोर्ट न करें।

---

## Who should open what / किसे क्या खोलना है

| Role / रोल | Use / इस्तेमाल |
| --- | --- |
| Super Admin | All modules. सभी मॉड्यूल। |
| Admin / Manager | Only the menus ticked on their role. रोल में जो टिक है वही। |
| State Head | Own state: districts, fleets, bookings, revenue. अपना राज्य। |
| District Head | Own district: drivers, vehicles, fleet cut. अपना जिला। |
| Fleet Owner | Own vehicles, own drivers, own reports. अपना फ्लीट। |

A driver does not use this admin site for trips. The driver uses the driver app. Customer bookings are made in the customer app.

ड्राइवर ट्रिप के लिए यह एडमिन साइट नहीं खोलता। वह ड्राइवर ऐप इस्तेमाल करता है। कस्टमर बुकिंग कस्टमर ऐप में होती है।
