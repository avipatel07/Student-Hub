const registrationForm =
    document.getElementById("registrationForm");


if (registrationForm) {


    /* ================= REGEX PATTERNS ================= */


    // Name: letters and spaces only
    const nameRegex =
        /^[A-Za-z]+(?:\s[A-Za-z]+)+$/;


    // Email validation
    const emailRegex =
        /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;


    // Indian 10-digit mobile number
    const mobileRegex =
        /^[6-9]\d{9}$/;


    // Password:
    // minimum 8 characters
    // one uppercase
    // one lowercase
    // one number
    const passwordRegex =
        /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;


    /* ================= FORM ELEMENTS ================= */


    const nameInput =
        document.getElementById("studentName");


    const emailInput =
        document.getElementById("studentEmail");


    const mobileInput =
        document.getElementById("studentMobile");


    const passwordInput =
        document.getElementById("studentPassword");


    const confirmPasswordInput =
        document.getElementById("confirmPassword");


    const courseInput =
        document.getElementById("course");


    const yearInput =
        document.getElementById("year");


    const termsInput =
        document.getElementById("terms");


    const successMessage =
        document.getElementById("registrationSuccess");


    /* ================= ERROR FUNCTION ================= */


    function showError(input, errorId, message) {

        const error =
            document.getElementById(errorId);


        if (input) {

            input.classList.add("input-error");

            input.classList.remove("input-success");

        }


        if (error) {

            error.textContent = message;

        }

    }


    /* ================= SUCCESS FUNCTION ================= */


    function showValid(input, errorId) {

        const error =
            document.getElementById(errorId);


        if (input) {

            input.classList.remove("input-error");

            input.classList.add("input-success");

        }


        if (error) {

            error.textContent = "";

        }

    }


    /* ================= CLEAR ERROR ================= */


    function clearValidation(input, errorId) {

        const error =
            document.getElementById(errorId);


        if (input) {

            input.classList.remove("input-error");

            input.classList.remove("input-success");

        }


        if (error) {

            error.textContent = "";

        }

    }


    /* ================= NAME VALIDATION ================= */


    function validateName() {

        const name =
            nameInput.value.trim();


        if (name === "") {

            showError(
                nameInput,
                "nameError",
                "Full name is required."
            );

            return false;

        }


        if (!nameRegex.test(name)) {

            showError(
                nameInput,
                "nameError",
                "Enter a valid name using letters and spaces only."
            );

            return false;

        }


        showValid(nameInput, "nameError");

        return true;

    }


    /* ================= EMAIL VALIDATION ================= */


    function validateEmail() {

        const email =
            emailInput.value.trim();


        if (email === "") {

            showError(
                emailInput,
                "emailError",
                "Email address is required."
            );

            return false;

        }


        if (!emailRegex.test(email)) {

            showError(
                emailInput,
                "emailError",
                "Enter a valid email address."
            );

            return false;

        }


        showValid(emailInput, "emailError");

        return true;

    }


    /* ================= MOBILE VALIDATION ================= */


    function validateMobile() {

        const mobile =
            mobileInput.value.trim();


        if (mobile === "") {

            showError(
                mobileInput,
                "mobileError",
                "Mobile number is required."
            );

            return false;

        }


        if (!mobileRegex.test(mobile)) {

            showError(
                mobileInput,
                "mobileError",
                "Enter a valid 10-digit Indian mobile number."
            );

            return false;

        }


        showValid(mobileInput, "mobileError");

        return true;

    }


    /* ================= PASSWORD VALIDATION ================= */


    function validatePassword() {

        const password =
            passwordInput.value;


        if (password === "") {

            showError(
                passwordInput,
                "passwordError",
                "Password is required."
            );

            return false;

        }


        if (!passwordRegex.test(password)) {

            showError(
                passwordInput,
                "passwordError",
                "Password must contain at least 8 characters, one uppercase letter, one lowercase letter and one number."
            );

            return false;

        }


        showValid(passwordInput, "passwordError");

        return true;

    }


    /* ================= CONFIRM PASSWORD ================= */


    function validateConfirmPassword() {

        const password =
            passwordInput.value;


        const confirmPassword =
            confirmPasswordInput.value;


        if (confirmPassword === "") {

            showError(
                confirmPasswordInput,
                "confirmPasswordError",
                "Please confirm your password."
            );

            return false;

        }


        if (password !== confirmPassword) {

            showError(
                confirmPasswordInput,
                "confirmPasswordError",
                "Passwords do not match."
            );

            return false;

        }


        showValid(
            confirmPasswordInput,
            "confirmPasswordError"
        );

        return true;

    }


    /* ================= COURSE ================= */


    function validateCourse() {

        if (courseInput.value === "") {

            showError(
                courseInput,
                "courseError",
                "Please select your course."
            );

            return false;

        }


        showValid(courseInput, "courseError");

        return true;

    }


    /* ================= YEAR ================= */


    function validateYear() {

        if (yearInput.value === "") {

            showError(
                yearInput,
                "yearError",
                "Please select your academic year."
            );

            return false;

        }


        showValid(yearInput, "yearError");

        return true;

    }


    /* ================= GENDER ================= */


    function validateGender() {

        const selectedGender =
            document.querySelector(
                'input[name="gender"]:checked'
            );


        const error =
            document.getElementById("genderError");


        if (!selectedGender) {

            error.textContent =
                "Please select your gender.";

            return false;

        }


        error.textContent = "";

        return true;

    }


    /* ================= TERMS ================= */


    function validateTerms() {

        const error =
            document.getElementById("termsError");


        if (!termsInput.checked) {

            error.textContent =
                "You must accept the Terms & Conditions.";

            return false;

        }


        error.textContent = "";

        return true;

    }


    /* ================= FORM SUBMIT ================= */


    registrationForm.addEventListener(
        "submit",
        function (event) {

            event.preventDefault();


            const validName =
                validateName();


            const validEmail =
                validateEmail();


            const validMobile =
                validateMobile();


            const validPassword =
                validatePassword();


            const validConfirmPassword =
                validateConfirmPassword();


            const validCourse =
                validateCourse();


            const validYear =
                validateYear();


            const validGender =
                validateGender();


            const validTerms =
                validateTerms();


            const formIsValid =
                validName &&
                validEmail &&
                validMobile &&
                validPassword &&
                validConfirmPassword &&
                validCourse &&
                validYear &&
                validGender &&
                validTerms;


            if (formIsValid) {

                successMessage.classList.add("show");


                successMessage.scrollIntoView({
                    behavior: "smooth",
                    block: "center"
                });


                console.log(
                    "Student registration successful"
                );

            }

            else {

                successMessage.classList.remove("show");

            }

        }
    );


    /* ================= LIVE VALIDATION ================= */


    nameInput.addEventListener(
        "blur",
        validateName
    );


    emailInput.addEventListener(
        "blur",
        validateEmail
    );


    mobileInput.addEventListener(
        "blur",
        validateMobile
    );


    passwordInput.addEventListener(
        "blur",
        validatePassword
    );


    confirmPasswordInput.addEventListener(
        "blur",
        validateConfirmPassword
    );


    courseInput.addEventListener(
        "change",
        validateCourse
    );


    yearInput.addEventListener(
        "change",
        validateYear
    );


    termsInput.addEventListener(
        "change",
        validateTerms
    );


    document
        .querySelectorAll(
            'input[name="gender"]'
        )
        .forEach(function (radio) {

            radio.addEventListener(
                "change",
                validateGender
            );

        });


    /* ================= RESET FORM ================= */


    const resetButton =
        document.getElementById(
            "resetRegistration"
        );


    if (resetButton) {

        resetButton.addEventListener(
            "click",
            function () {

                setTimeout(function () {

                    document
                        .querySelectorAll(
                            ".error-message"
                        )
                        .forEach(function (error) {

                            error.textContent = "";

                        });


                    document
                        .querySelectorAll(
                            ".input-error, .input-success"
                        )
                        .forEach(function (input) {

                            input.classList.remove(
                                "input-error"
                            );

                            input.classList.remove(
                                "input-success"
                            );

                        });


                    successMessage.classList.remove(
                        "show"
                    );

                }, 0);

            }
        );

    }

}

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