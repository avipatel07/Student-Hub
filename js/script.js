/* ==========================================
   1. HAMBURGER MENU
   ========================================== */

const menuButton = document.querySelector(".menu-toggle");
const navigation = document.querySelector(".nav-container");

if (menuButton && navigation) {

    menuButton.addEventListener("click", function () {

        navigation.classList.toggle("active");

        const isOpen = navigation.classList.contains("active");

        menuButton.setAttribute("aria-expanded", isOpen);

    });
}


/* ==========================================
   2. LIGHT / DARK THEME SWITCHER
   ========================================== */

const themeButton = document.querySelector(".theme-toggle");

if (themeButton) {

    const savedTheme = localStorage.getItem("studenthub-theme");

    if (savedTheme === "dark") {
        document.body.classList.add("dark-theme");
        themeButton.textContent = "☀️";
    }

    themeButton.addEventListener("click", function () {

        document.body.classList.toggle("dark-theme");

        const darkMode =
            document.body.classList.contains("dark-theme");

        if (darkMode) {

            localStorage.setItem(
                "studenthub-theme",
                "dark"
            );

            themeButton.textContent = "☀️";

        } else {

            localStorage.setItem(
                "studenthub-theme",
                "light"
            );

            themeButton.textContent = "🌙";
        }

    });
}


/* ==========================================
   3. NOTIFICATION BANNER
   ========================================== */

const notification = document.querySelector(".notification-banner");
const closeNotification =
    document.querySelector(".close-notification");

if (notification && closeNotification) {

    closeNotification.addEventListener("click", function () {

        notification.style.display = "none";

    });
}


/* ==========================================
   4. COLLAPSIBLE FAQ
   ========================================== */

const faqQuestions =
    document.querySelectorAll(".faq-question");

faqQuestions.forEach(function (question) {

    question.addEventListener("click", function () {

        const answer =
            question.nextElementSibling;

        const isOpen =
            answer.classList.contains("show");


        /* Close other FAQ answers */

        document
            .querySelectorAll(".faq-answer")
            .forEach(function (item) {

                item.classList.remove("show");

            });


        document
            .querySelectorAll(".faq-question")
            .forEach(function (item) {

                item.classList.remove("active");

            });


        /* Open selected answer */

        if (!isOpen) {

            answer.classList.add("show");

            question.classList.add("active");

        }

    });

});


/* ==========================================
   5. CONTENT SLIDER
   ========================================== */

const slides =
    document.querySelectorAll(".slide");

const previousButton =
    document.querySelector(".slider-prev");

const nextButton =
    document.querySelector(".slider-next");

let currentSlide = 0;


function showSlide(index) {

    if (slides.length === 0) {
        return;
    }

    if (index >= slides.length) {
        currentSlide = 0;
    }

    else if (index < 0) {
        currentSlide = slides.length - 1;
    }

    else {
        currentSlide = index;
    }


    slides.forEach(function (slide, index) {

        slide.classList.remove("active");

        if (index === currentSlide) {

            slide.classList.add("active");

        }

    });

}


if (nextButton) {

    nextButton.addEventListener("click", function () {

        showSlide(currentSlide + 1);

    });

}


if (previousButton) {

    previousButton.addEventListener("click", function () {

        showSlide(currentSlide - 1);

    });

}


/* Automatic slider */

if (slides.length > 0) {

    setInterval(function () {

        showSlide(currentSlide + 1);

    }, 5000);

    showSlide(0);
}


/* ==========================================
   6. MODAL POPUP
   ========================================== */

const modal =
    document.querySelector(".event-modal");

const modalTitle =
    document.querySelector(".modal-title");

const modalText =
    document.querySelector(".modal-text");

const modalClose =
    document.querySelector(".modal-close");

const registerButtons =
    document.querySelectorAll(".event-register");


registerButtons.forEach(function (button) {

    button.addEventListener("click", function () {

        const eventName =
            button.getAttribute("data-event");

        if (modalTitle) {

            modalTitle.textContent =
                "Register for " + eventName;

        }

        if (modalText) {

            modalText.textContent =
                "You are registering for " +
                eventName +
                ". Please confirm your registration.";

        }

        if (modal) {

            modal.classList.add("show");

        }

    });

});


if (modalClose) {

    modalClose.addEventListener("click", function () {

        modal.classList.remove("show");

    });

}


/* Close modal when clicking outside */

if (modal) {

    modal.addEventListener("click", function (event) {

        if (event.target === modal) {

            modal.classList.remove("show");

        }

    });

}


/* ==========================================
   MODAL CONFIRM BUTTON
   ========================================== */

const confirmButton =
    document.querySelector(".modal-confirm");

if (confirmButton) {

    confirmButton.addEventListener("click", function () {

        alert(
            "Registration request submitted successfully!"
        );

        if (modal) {

            modal.classList.remove("show");

        }

    });

}