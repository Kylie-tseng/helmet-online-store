document.addEventListener('DOMContentLoaded', () => {
    const spinBtn = document.getElementById('spinBtn');
    const wheelImg = document.getElementById('wheelImg');

    let isSpinning = false;
    let currentRotation = 0;

    spinBtn.addEventListener('click', async () => {
        if (isSpinning) return;

        isSpinning = true;
        spinBtn.disabled = true;
        spinBtn.innerText = "旋轉中...";

        try {
            const response = await fetch('api/get_prize.php');
            const prize = await response.json();

            // 🎯 核心角度計算
            const extraDegree = 360 - prize.angle;
            const totalSpin = 1800 + extraDegree;

            // 避免角度爆炸
            currentRotation = currentRotation % 360;
            currentRotation += totalSpin;

            wheelImg.style.transform = `rotate(${currentRotation}deg)`;

            setTimeout(() => {
                if (prize.code) {
                    alert(`恭喜獲得：${prize.name}！\n優惠碼：${prize.code}`);
                } else {
                    alert(`可惜！這次是「${prize.name}」`);
                }

                isSpinning = false;
                spinBtn.disabled = false;
                spinBtn.innerText = "再次抽獎";
            }, 4000);

        } catch (error) {
            alert('系統錯誤');
            isSpinning = false;
            spinBtn.disabled = false;
        }
    });
});