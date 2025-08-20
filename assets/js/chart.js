document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("report-modal");
    const openBtn = document.getElementById("view-report-btn");
    const closeBtn = document.getElementById("close-report-btn");
    let chartInstance = null;

    openBtn.addEventListener("click", function () {
        modal.style.display = "block";

        fetch("' . admin_url('admin-ajax.php?action=fetch_overall_report_json') . '")
            .then(res => res.json())
            .then(data => {
                document.getElementById("s-ratio").innerHTML = "Success Ratio: " + data.success_ratio + "%";
                const ctx = document.getElementById("reportChart").getContext("2d");

                if (chartInstance) {
                    chartInstance.destroy();
                }

                chartInstance = new Chart(ctx, {
                    type: "pie",
                    data: {
                        labels: ["Confirmed", "Failed", "None/Unspecified"],
                        datasets: [{
                            label: "Order Status",
                            data: [data.confirmed, data.failed, data.none],
                            backgroundColor: [
                                "rgba(75, 192, 192, 0.7)",  // confirmed
                                "rgba(255, 99, 132, 0.7)",  // failed
                                "rgba(201, 203, 207, 0.7)"  // none
                            ],
                            borderColor: [
                                "rgba(75, 192, 192, 1)",
                                "rgba(255, 99, 132, 1)",
                                "rgba(201, 203, 207, 1)"
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true
                    }
                });
            })
            .catch(err => {
                alert("Failed to load chart data.");
            });
    });

    closeBtn.addEventListener("click", function () {
        modal.style.display = "none";
    });

    window.addEventListener("click", function (e) {
        if (e.target == modal) {
            modal.style.display = "none";
        }
    });
});
    