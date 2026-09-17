# Massage Therapist Website

A single-page massage therapist website for visitors from Kohtla-Jarve and Johvi.

**Author:** Hanna Zozulia

## Features

- responsive desktop and mobile navigation;
- hero section;
- information about the massage approach and benefits;
- massage services with duration and pricing;
- session process description;
- massage room atmosphere gallery;
- client testimonials;
- expandable FAQ section;
- online booking form;
- service, date, time, and confirmation method selection;
- required field and date validation;
- booking submission success state;
- gift certificate section;
- contact details, opening hours, and location;
- SEO and Open Graph metadata.

## Running the Project

The project does not use a build tool or npm dependencies. You can open `index.html` directly in a browser.

To run it with a simple local HTTP server, execute the following command from the project root:

```powershell
python -m http.server 8000
```

Then open the following address in your browser:

<http://localhost:8000>

## Project Structure

```text
.
├── index.html   # Page markup and text content
├── styles.css   # Styles and responsive layout
├── script.js    # Navigation, FAQ, and booking form logic
└── img/         # Website images and logo
```

## External Resources

The page loads the following resources through CDNs and Google Fonts:

- Tailwind CSS 3.4.17;
- Lucide 0.263.0;
- Alegreya Sans and Prata fonts.

An internet connection is required to load these resources. Local images are stored in the `img/` directory.

## Booking Form

The form runs in the browser. It validates required fields, prevents selecting a past date, and displays a confirmation with the selected service, date, and time after submission.

Server-side form submission is not configured in the current version.
