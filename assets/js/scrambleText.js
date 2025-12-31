// glitch style for text I found interesting and decided to replicate it

document.addEventListener("DOMContentLoaded", () => {
    const scrambleElements = document.querySelectorAll(".scramble-text");
    const chars = "!<>-_\\/[]{}—=+*^?#________1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ";

    scrambleElements.forEach(el => {
        const originalText = el.dataset.text || el.innerText;

        el.addEventListener("mouseenter", () => {
            let iteration = 0;
            const maxIterations = 5; // how many frames of glitch
            const intervalSpeed = 60; // how fast each frame changes
            const interval = setInterval(() => {
                el.innerText = originalText
                    .split("")
                    .map(() => chars[Math.floor(Math.random() * chars.length)])
                    .join("");

                iteration++;

                if (iteration >= maxIterations) {
                    clearInterval(interval);
                    el.innerText = originalText; // reset to normal text
                }
            }, intervalSpeed);
        });
    });
});
