import "./bootstrap";
// Modal Handling
document.addEventListener("DOMContentLoaded", function () {
    // Gagal Proses Card
    document
        .getElementById("gagalProsesCard")
        ?.addEventListener("click", () => {
            new bootstrap.Modal(
                document.getElementById("gagalProsesModal")
            ).show();
        });

    // Image Preview
    document.querySelectorAll("[data-image-preview]").forEach((img) => {
        img.addEventListener("click", () =>
            showImageModal(img.dataset.imagePreview)
        );
    });
});

function showImageModal(fileUrl) {
    // Image preview logic
}
