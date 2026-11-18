const form = document.getElementById("form");
const tableBody = document.getElementById("table").getElementsByTagName('tbody')[0];
const submitButton = form.querySelector('button[type="submit"]');
let editMode = false;
let editId = null;

// إضافة أو تحديث منتج
form.addEventListener("submit", function (e) {
    e.preventDefault();
    const productData = {
        name: document.getElementById("name").value,
        price: document.getElementById("price").value,
        count: document.getElementById("count").value
    };

    if (editMode) {
        // تحديث منتج
        fetch("update_product.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `id=${editId}&name=${productData.name}&price=${productData.price}&count=${productData.count}`
        })
            .then(response => response.text())
            .then(data => {
                if (data.trim() === "success") {
                    const row = tableBody.querySelector(`button.edit[data-id='${editId}']`).closest("tr");
                    row.cells[0].innerText = productData.name;
                    row.cells[1].innerText = productData.price;
                    row.cells[2].innerText = productData.count;

                    submitButton.innerText = "إضافة منتج";
                    submitButton.style.backgroundColor = ""; // إعادة لون الزر للأصل
                    form.reset();
                    editMode = false;
                    editId = null;
                } else {
                    alert("حصل خطأ: " + data);
                }
            });
    } else {
        // إضافة منتج جديد
        fetch("products_process.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `name=${productData.name}&price=${productData.price}&count=${productData.count}`
        })
            .then(response => response.text())
            .then(data => {
                if (data.trim() === "success") {
                    const newRow = tableBody.insertRow(0);
                    newRow.insertCell(0).innerText = productData.name;
                    newRow.insertCell(1).innerText = productData.price;
                    newRow.insertCell(2).innerText = productData.count;
                    newRow.insertCell(3).innerHTML = `<button class='edit' data-id='${data.new_id || ''}'>تعديل</button> <button class='delete' data-id='${data.new_id || ''}'>حذف</button>`;
                    submitButton.style.backgroundColor = ""; // إعادة لون الزر للأصل
                    form.reset();
                    location.reload(); // لتحديث ال IDs
                } else {
                    alert("حصل خطأ: " + data);
                }
            });
    }
});

// حذف منتج
tableBody.addEventListener("click", function (e) {
    if (e.target.classList.contains("delete")) {
        const id = e.target.getAttribute("data-id");
        if (confirm("هل أنت متأكد من الحذف؟")) {
            fetch("delete_product.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `id=${id}`
            })
                .then(response => response.text())
                .then(data => {
                    if (data.trim() === "success") {
                        e.target.closest("tr").remove();
                        if (editMode && editId == id) {
                            submitButton.innerText = "إضافة منتج";
                            submitButton.style.backgroundColor = ""; // إعادة لون الزر للأصل
                            form.reset();
                            editMode = false;
                            editId = null;
                        }
                    } else {
                        alert("حصل خطأ: " + data);
                    }
                });
        }
    }
});

// تعديل منتج
tableBody.addEventListener("click", function (e) {
    if (e.target.classList.contains("edit")) {
        const row = e.target.closest("tr");
        editId = e.target.getAttribute("data-id");

        document.getElementById("name").value = row.cells[0].innerText;
        document.getElementById("price").value = row.cells[1].innerText;
        document.getElementById("count").value = row.cells[2].innerText;

        submitButton.innerText = "تحديث البيانات";
        submitButton.style.backgroundColor = "#ffa500"; // تغيير اللون للأصفر
        editMode = true;
    }
});
